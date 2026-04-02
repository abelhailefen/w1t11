<?php

namespace App\Service;

use App\Enum\AppointmentState;
use App\Enum\AppointmentSlotStatus;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;

class BookingService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogService $auditLogService,
        private readonly SystemSettingService $systemSettingService
    ) {
    }

    public function holdSlot(int $slotId, int $userId): int
    {
        return (int) $this->connection->transactional(function (Connection $connection) use ($slotId, $userId): int {
            $slot = $connection->fetchAssociative('SELECT * FROM appointment_slots WHERE id = :id FOR UPDATE', ['id' => $slotId]);
            if (!$slot) {
                throw new ApiException('Slot not found', 404);
            }
            $this->assertFutureWindow(new \DateTimeImmutable((string) $slot['start_at']));
            if ((int) $slot['available_count'] <= 0 || (string) $slot['status'] !== AppointmentSlotStatus::AVAILABLE->value) {
                throw new ApiException('Slot is no longer available', 409);
            }
            $activeClaim = (int) $connection->fetchOne(
                'SELECT COUNT(id) FROM appointments WHERE slot_id = :slotId AND ((state = :held AND held_until > :now) OR state = :confirmed)',
                [
                    'slotId' => $slotId,
                    'held' => AppointmentState::HELD->value,
                    'confirmed' => AppointmentState::CONFIRMED->value,
                    'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]
            );
            if ($activeClaim > 0) {
                throw new ApiException('Slot is no longer available', 409);
            }

            $this->assertNoConfirmedOverlap((int) $slot['practitioner_id'], (int) $slot['location_id'], (string) $slot['start_at'], (string) $slot['end_at']);
            $holdUntil = (new \DateTimeImmutable())->modify(sprintf('+%d minutes', $this->holdMinutes()));
            $now = new \DateTimeImmutable();
            $connection->insert('appointments', [
                'practitioner_id' => (int) $slot['practitioner_id'],
                'location_id' => (int) $slot['location_id'],
                'slot_id' => $slotId,
                'booked_by' => $userId,
                'state' => AppointmentState::HELD->value,
                'held_until' => $holdUntil->format('Y-m-d H:i:s'),
                'reschedule_count' => 0,
                'booked_at' => $now->format('Y-m-d H:i:s'),
                'cancelled_at' => null,
            ]);

            $appointmentId = (int) $connection->lastInsertId();
            $this->auditLogService->log($userId, 'CREATE', 'Appointment', $appointmentId, null, ['slot_id' => $slotId], null);
            return $appointmentId;
        });
    }

    public function confirmBooking(int $appointmentId, int $userId): void
    {
        $this->connection->transactional(function (Connection $connection) use ($appointmentId, $userId): void {
            $appointment = $connection->fetchAssociative('SELECT * FROM appointments WHERE id = :id FOR UPDATE', ['id' => $appointmentId]);
            if (!$appointment) {
                throw new ApiException('Appointment not found', 404);
            }
            if ((int) ($appointment['booked_by'] ?? 0) !== $userId) {
                throw new ApiException('Forbidden', 403);
            }
            if ((string) $appointment['state'] !== AppointmentState::HELD->value) {
                throw new ApiException('Appointment is not on hold', 422);
            }

            $heldUntil = $appointment['held_until'] ? new \DateTimeImmutable((string) $appointment['held_until']) : null;
            if (!$heldUntil || $heldUntil < new \DateTimeImmutable()) {
                throw new ApiException('Hold expired', 410);
            }

            $slot = $connection->fetchAssociative('SELECT * FROM appointment_slots WHERE id = :id FOR UPDATE', ['id' => (int) $appointment['slot_id']]);
            if (!$slot || (int) $slot['available_count'] <= 0) {
                throw new ApiException('Slot is no longer available', 409);
            }
            $this->assertFutureWindow(new \DateTimeImmutable((string) $slot['start_at']));
            $this->assertNoConfirmedOverlap((int) $slot['practitioner_id'], (int) $slot['location_id'], (string) $slot['start_at'], (string) $slot['end_at'], $appointmentId);

            $connection->executeStatement(
                'UPDATE appointment_slots SET available_count = available_count - 1, status = CASE WHEN available_count - 1 <= 0 THEN :full ELSE status END WHERE id = :id',
                ['id' => (int) $slot['id'], 'full' => AppointmentSlotStatus::FULL->value]
            );
            $connection->update('appointments', [
                'state' => AppointmentState::CONFIRMED->value,
                'held_until' => null,
            ], ['id' => $appointmentId]);
        });
    }

    public function reschedule(int $appointmentId, int $newSlotId, int $userId): void
    {
        $this->connection->transactional(function (Connection $connection) use ($appointmentId, $newSlotId, $userId): void {
            $appointment = $connection->fetchAssociative('SELECT * FROM appointments WHERE id = :id FOR UPDATE', ['id' => $appointmentId]);
            if (!$appointment) {
                throw new ApiException('Appointment not found', 404);
            }
            if ((int) ($appointment['booked_by'] ?? 0) !== $userId && !$this->isAdmin($userId)) {
                throw new ApiException('Forbidden', 403);
            }
            if ((string) $appointment['state'] !== AppointmentState::CONFIRMED->value) {
                throw new ApiException('Only confirmed appointments can be rescheduled', 422);
            }
            if ((int) $appointment['reschedule_count'] >= 2) {
                throw new ApiException('Reschedule limit reached', 422);
            }

            $oldSlot = $connection->fetchAssociative('SELECT * FROM appointment_slots WHERE id = :id FOR UPDATE', ['id' => (int) $appointment['slot_id']]);
            $newSlot = $connection->fetchAssociative('SELECT * FROM appointment_slots WHERE id = :id FOR UPDATE', ['id' => $newSlotId]);
            if (!$oldSlot || !$newSlot) {
                throw new ApiException('Slot not found', 404);
            }
            if ((int) $newSlot['available_count'] <= 0 || (string) $newSlot['status'] === AppointmentSlotStatus::CANCELLED->value) {
                throw new ApiException('New slot is unavailable', 409);
            }
            $this->assertFutureWindow(new \DateTimeImmutable((string) $newSlot['start_at']));
            $this->assertNoConfirmedOverlap((int) $newSlot['practitioner_id'], (int) $newSlot['location_id'], (string) $newSlot['start_at'], (string) $newSlot['end_at'], $appointmentId);

            $connection->executeStatement('UPDATE appointment_slots SET available_count = available_count + 1, status = :available WHERE id = :id', [
                'id' => (int) $oldSlot['id'],
                'available' => AppointmentSlotStatus::AVAILABLE->value,
            ]);
            $connection->executeStatement(
                'UPDATE appointment_slots SET available_count = available_count - 1, status = CASE WHEN available_count - 1 <= 0 THEN :full ELSE :available END WHERE id = :id',
                ['id' => $newSlotId, 'full' => AppointmentSlotStatus::FULL->value, 'available' => AppointmentSlotStatus::AVAILABLE->value]
            );

            $connection->update('appointments', [
                'slot_id' => $newSlotId,
                'practitioner_id' => (int) $newSlot['practitioner_id'],
                'location_id' => (int) $newSlot['location_id'],
                'reschedule_count' => (int) $appointment['reschedule_count'] + 1,
            ], ['id' => $appointmentId]);

            $connection->insert('appointment_reschedule_history', [
                'appointment_id' => $appointmentId,
                'old_slot_id' => (int) $oldSlot['id'],
                'new_slot_id' => $newSlotId,
                'changed_by' => $userId,
                'changed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
            $this->auditLogService->log($userId, 'RESCHEDULE', 'Appointment', $appointmentId, ['slot_id' => (int) $oldSlot['id']], ['slot_id' => $newSlotId], null);
        });
    }

    public function cancel(int $appointmentId, int $userId): void
    {
        $this->connection->transactional(function (Connection $connection) use ($appointmentId, $userId): void {
            $appointment = $connection->fetchAssociative('SELECT * FROM appointments WHERE id = :id FOR UPDATE', ['id' => $appointmentId]);
            if (!$appointment) {
                throw new ApiException('Appointment not found', 404);
            }
            if ((int) ($appointment['booked_by'] ?? 0) !== $userId && !$this->isAdmin($userId)) {
                throw new ApiException('Forbidden', 403);
            }
            if (!in_array((string) $appointment['state'], [AppointmentState::CONFIRMED->value, AppointmentState::HELD->value], true)) {
                throw new ApiException('Appointment cannot be cancelled', 422);
            }

            $slot = $connection->fetchAssociative('SELECT * FROM appointment_slots WHERE id = :id FOR UPDATE', ['id' => (int) $appointment['slot_id']]);
            if (!$slot) {
                throw new ApiException('Slot not found', 404);
            }

            $startsIn = (new \DateTimeImmutable((string) $slot['start_at']))->getTimestamp() - time();
            if ($startsIn < (24 * 3600) && !$this->isAdmin($userId)) {
                throw new ApiException('Cannot cancel within 24 hours unless system admin', 403);
            }

            if ((string) $appointment['state'] === AppointmentState::CONFIRMED->value) {
                $connection->executeStatement('UPDATE appointment_slots SET available_count = available_count + 1, status = :available WHERE id = :id', [
                    'id' => (int) $slot['id'],
                    'available' => AppointmentSlotStatus::AVAILABLE->value,
                ]);
            }

            $connection->update('appointments', [
                'state' => AppointmentState::CANCELLED->value,
                'cancelled_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'held_until' => null,
            ], ['id' => $appointmentId]);
            $this->auditLogService->log($userId, 'CANCEL', 'Appointment', $appointmentId, ['state' => (string) $appointment['state']], ['state' => 'CANCELLED'], null);
        });
    }

    public function cleanupExpiredHolds(): int
    {
        return $this->connection->transactional(function (Connection $connection): int {
            return $connection->executeStatement(
                'UPDATE appointments SET state = :cancelled, cancelled_at = :now WHERE state = :held AND held_until IS NOT NULL AND held_until < :now',
                [
                    'cancelled' => AppointmentState::CANCELLED->value,
                    'held' => AppointmentState::HELD->value,
                    'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]
            );
        });
    }

    private function assertNoConfirmedOverlap(int $practitionerId, int $locationId, string $startAt, string $endAt, ?int $excludeAppointmentId = null): void
    {
        $sql = 'SELECT COUNT(a.id) FROM appointments a INNER JOIN appointment_slots s ON s.id = a.slot_id WHERE a.state = :state AND a.practitioner_id = :pid AND a.location_id = :lid AND s.start_at < :endAt AND s.end_at > :startAt';
        $params = [
            'state' => AppointmentState::CONFIRMED->value,
            'pid' => $practitionerId,
            'lid' => $locationId,
            'startAt' => $startAt,
            'endAt' => $endAt,
        ];
        if ($excludeAppointmentId) {
            $sql .= ' AND a.id != :excludeId';
            $params['excludeId'] = $excludeAppointmentId;
        }

        $count = (int) $this->connection->fetchOne($sql, $params);
        if ($count > 0) {
            throw new ApiException('Scheduling conflict detected', 422);
        }
    }

    private function assertFutureWindow(\DateTimeImmutable $slotStart): void
    {
        if ($slotStart > (new \DateTimeImmutable())->modify('+90 days')) {
            throw new ApiException('Cannot book slots more than 90 days ahead', 422);
        }
    }

    private function holdMinutes(): int
    {
        $fallback = max(1, (int) ($_ENV['APPOINTMENT_HOLD_MINUTES'] ?? $_SERVER['APPOINTMENT_HOLD_MINUTES'] ?? 5));
        return max(1, $this->systemSettingService->getInt('appointment_hold_minutes', $fallback));
    }

    private function isAdmin(int $userId): bool
    {
        $role = $this->connection->fetchOne('SELECT role FROM users WHERE id = :id', ['id' => $userId]);
        return $role === 'ROLE_SYSTEM_ADMIN';
    }
}
