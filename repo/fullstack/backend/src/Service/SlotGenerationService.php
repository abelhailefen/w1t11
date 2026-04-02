<?php

namespace App\Service;

use App\Enum\AppointmentSlotStatus;
use Doctrine\DBAL\Connection;

class SlotGenerationService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SystemSettingService $systemSettingService
    )
    {
    }

    public function generate(string $dateFrom, string $dateTo, ?int $practitionerId = null): int
    {
        $from = new \DateTimeImmutable($dateFrom . ' 00:00:00');
        $to = new \DateTimeImmutable($dateTo . ' 23:59:59');
        if ($to < $from) {
            throw new ApiException('Invalid date range', 400);
        }

        $windows = $this->connection->fetchAllAssociative(
            'SELECT * FROM availability_windows WHERE practitioner_id IS NOT NULL' . ($practitionerId ? ' AND practitioner_id = :pid' : ''),
            $practitionerId ? ['pid' => $practitionerId] : []
        );

        $created = 0;
        foreach ($windows as $window) {
            $cursor = $from;
            while ($cursor <= $to) {
                if ((int) $cursor->format('w') !== (int) $window['weekday']) {
                    $cursor = $cursor->modify('+1 day');
                    continue;
                }

                $start = new \DateTimeImmutable($cursor->format('Y-m-d') . ' ' . $window['start_time']);
                $end = new \DateTimeImmutable($cursor->format('Y-m-d') . ' ' . $window['end_time']);
                $minutes = max(1, (int) ($window['slot_minutes'] ?: $this->defaultSlotMinutes()));
                $slotStart = $start;
                while ($slotStart < $end) {
                    $slotEnd = $slotStart->modify(sprintf('+%d minutes', $minutes));
                    if ($slotEnd > $end) {
                        break;
                    }

                    $exists = $this->connection->fetchOne(
                        'SELECT id FROM appointment_slots WHERE practitioner_id = :pid AND location_id = :lid AND start_at = :s AND end_at = :e',
                        [
                            'pid' => (int) $window['practitioner_id'],
                            'lid' => $this->defaultLocationId(),
                            's' => $slotStart->format('Y-m-d H:i:s'),
                            'e' => $slotEnd->format('Y-m-d H:i:s'),
                        ]
                    );
                    if (!$exists) {
                        $this->connection->insert('appointment_slots', [
                            'practitioner_id' => (int) $window['practitioner_id'],
                            'location_id' => $this->defaultLocationId(),
                            'start_at' => $slotStart->format('Y-m-d H:i:s'),
                            'end_at' => $slotEnd->format('Y-m-d H:i:s'),
                            'capacity' => 1,
                            'available_count' => 1,
                            'status' => AppointmentSlotStatus::AVAILABLE->value,
                        ]);
                        $created++;
                    }

                    $slotStart = $slotEnd;
                }

                $cursor = $cursor->modify('+1 day');
            }
        }

        return $created;
    }

    private function defaultSlotMinutes(): int
    {
        $fallback = max(5, (int) ($_ENV['APPOINTMENT_SLOT_MINUTES'] ?? $_SERVER['APPOINTMENT_SLOT_MINUTES'] ?? 30));
        return max(5, $this->systemSettingService->getInt('appointment_slot_minutes', $fallback));
    }

    private function defaultLocationId(): int
    {
        return (int) $this->connection->fetchOne('SELECT id FROM locations WHERE status = :status ORDER BY id ASC LIMIT 1', ['status' => 'ACTIVE']);
    }
}
