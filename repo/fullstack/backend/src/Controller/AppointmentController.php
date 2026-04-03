<?php

namespace App\Controller;

use App\Entity\AvailabilityWindow;
use App\Entity\Appointment;
use App\Entity\AppointmentSlot;
use App\Entity\User;
use App\Repository\AvailabilityWindowRepository;
use App\Repository\AppointmentRepository;
use App\Repository\AppointmentSlotRepository;
use App\Repository\PractitionerRepository;
use App\Service\ApiException;
use App\Service\BookingService;
use App\Service\SlotGenerationService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1')]
class AppointmentController extends ApiController
{
    public function __construct(
        private readonly AvailabilityWindowRepository $windowRepository,
        private readonly AppointmentSlotRepository $slotRepository,
        private readonly AppointmentRepository $appointmentRepository,
        private readonly PractitionerRepository $practitionerRepository,
        private readonly BookingService $bookingService,
        private readonly SlotGenerationService $slotGenerationService,
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/availability', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/availability', tags: ['Scheduling'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Availability windows')])]
    public function listAvailability(): JsonResponse
    {
        $items = $this->windowRepository->findBy([], ['id' => 'ASC']);
        return $this->ok(['items' => array_map(fn (AvailabilityWindow $w) => $this->windowToArray($w), $items)]);
    }

    #[Route('/availability', methods: ['PUT'])]
    #[OA\Put(path: '/api/v1/availability', tags: ['Scheduling'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Availability updated')])]
    public function updateAvailability(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($user->getRole()->value !== 'ROLE_SYSTEM_ADMIN') {
            return $this->error('System admin role required', 403);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $windowsPayload = $payload['windows'] ?? [];
        if (!is_array($windowsPayload)) {
            return $this->error('windows must be an array', 400);
        }

        foreach ($windowsPayload as $windowPayload) {
            $violations = $this->validator->validate($windowPayload, new Assert\Collection([
                'practitioner_id' => [new Assert\Required([new Assert\Positive()])],
                'weekday' => [new Assert\Required([new Assert\Range(min: 0, max: 6)])],
                'start_time' => [new Assert\Required([new Assert\NotBlank()])],
                'end_time' => [new Assert\Required([new Assert\NotBlank()])],
                'slot_minutes' => [new Assert\Optional([new Assert\Positive()])],
                'org_unit_id' => [new Assert\Optional([new Assert\Positive()])],
            ], allowExtraFields: true));
            if (count($violations) > 0) {
                return $this->validationError($violations);
            }

            $practitioner = $this->practitionerRepository->find((int) $windowPayload['practitioner_id']);
            if (!$practitioner) {
                return $this->error('Practitioner not found', 404);
            }

            $window = (new AvailabilityWindow())
                ->setPractitioner($practitioner)
                ->setWeekday((int) $windowPayload['weekday'])
                ->setStartTime(new \DateTimeImmutable('1970-01-01 ' . $windowPayload['start_time']))
                ->setEndTime(new \DateTimeImmutable('1970-01-01 ' . $windowPayload['end_time']))
                ->setSlotMinutes((int) ($windowPayload['slot_minutes'] ?? 30))
                ->setOrgUnitId(isset($windowPayload['org_unit_id']) ? (int) $windowPayload['org_unit_id'] : null);
            $this->entityManager->persist($window);
        }

        $this->entityManager->flush();
        return $this->ok(['message' => 'Availability windows updated']);
    }

    #[Route('/appointments/slots/generate', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/appointments/slots/generate', tags: ['Scheduling'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Slots generated')])]
    public function generateSlots(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if ($user->getRole()->value !== 'ROLE_SYSTEM_ADMIN') {
            return $this->error('System admin role required', 403);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        try {
            $created = $this->slotGenerationService->generate((string) $payload['date_from'], (string) $payload['date_to'], isset($payload['practitioner_id']) ? (int) $payload['practitioner_id'] : null);
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        return $this->ok(['created' => $created]);
    }

    #[Route('/appointments/slots', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/appointments/slots', tags: ['Scheduling'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Slot list')])]
    public function slots(Request $request): JsonResponse
    {
        $practitionerId = (int) $request->query->get('practitioner_id', 0);
        $from = (string) $request->query->get('date_from', (new \DateTimeImmutable())->format('Y-m-d'));
        $to = (string) $request->query->get('date_to', (new \DateTimeImmutable('+7 days'))->format('Y-m-d'));
        if ($practitionerId <= 0) {
            return $this->error('practitioner_id is required', 400);
        }

        $items = $this->slotRepository->findAvailableByRange($practitionerId, $from . ' 00:00:00', $to . ' 23:59:59');
        return $this->ok(['items' => array_map(fn (AppointmentSlot $s) => $this->slotToArray($s), $items)]);
    }

    #[Route('/appointments/hold', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/appointments/hold', tags: ['Scheduling'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Slot held')])]
    public function hold(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $payload = json_decode($request->getContent(), true) ?? [];
        try {
            $appointmentId = $this->bookingService->holdSlot((int) $payload['slot_id'], (int) $user->getId());
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }
        return $this->ok(['appointment_id' => $appointmentId]);
    }

    #[Route('/appointments/book', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/appointments/book', tags: ['Scheduling'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Booking confirmed')])]
    public function book(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $payload = json_decode($request->getContent(), true) ?? [];
        try {
            $this->bookingService->confirmBooking((int) $payload['appointment_id'], (int) $user->getId());
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }
        return $this->ok(['message' => 'Booking confirmed']);
    }

    #[Route('/appointments/{id}/reschedule', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/appointments/{id}/reschedule', tags: ['Scheduling'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Rescheduled')])]
    public function reschedule(int $id, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $payload = json_decode($request->getContent(), true) ?? [];
        try {
            $this->bookingService->reschedule($id, (int) $payload['new_slot_id'], (int) $user->getId());
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }
        return $this->ok(['message' => 'Appointment rescheduled']);
    }

    #[Route('/appointments/{id}/cancel', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/appointments/{id}/cancel', tags: ['Scheduling'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Cancelled')])]
    public function cancel(int $id): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        try {
            $this->bookingService->cancel($id, (int) $user->getId());
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }
        return $this->ok(['message' => 'Appointment cancelled']);
    }

    #[Route('/appointments/calendar', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/appointments/calendar', tags: ['Scheduling'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Calendar data')])]
    public function calendar(Request $request): JsonResponse
    {
        $week = (string) $request->query->get('week', (new \DateTimeImmutable())->format('Y-m-d'));
        $practitionerId = (int) $request->query->get('practitioner_id', 0);
        $weekStart = new \DateTimeImmutable($week);
        $weekEnd = $weekStart->modify('+6 days')->setTime(23, 59, 59);

        $params = [
            'from' => $weekStart->format('Y-m-d H:i:s'),
            'to' => $weekEnd->format('Y-m-d H:i:s'),
        ];
        $sql = 'SELECT a.id as appointment_id, a.state, s.start_at, s.end_at, p.full_name as practitioner_name, l.name as location_name
                FROM appointments a
                INNER JOIN appointment_slots s ON s.id = a.slot_id
                INNER JOIN practitioners p ON p.id = a.practitioner_id
                INNER JOIN locations l ON l.id = a.location_id
                WHERE s.start_at >= :from AND s.start_at <= :to';
        if ($practitionerId > 0) {
            $sql .= ' AND a.practitioner_id = :pid';
            $params['pid'] = $practitionerId;
        }
        $sql .= ' ORDER BY s.start_at ASC';
        $items = $this->connection->fetchAllAssociative($sql, $params);
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $weekStart->modify(sprintf('+%d days', $i))->format('Y-m-d');
            $days[$d] = [];
        }
        foreach ($items as $item) {
            $dayKey = (new \DateTimeImmutable((string) $item['start_at']))->format('Y-m-d');
            if (!array_key_exists($dayKey, $days)) {
                $days[$dayKey] = [];
            }
            $days[$dayKey][] = [
                'appointment_id' => (int) $item['appointment_id'],
                'state' => $item['state'],
                'start_at' => (new \DateTimeImmutable((string) $item['start_at']))->format(DATE_ATOM),
                'end_at' => (new \DateTimeImmutable((string) $item['end_at']))->format(DATE_ATOM),
                'practitioner_name' => $item['practitioner_name'],
                'location_name' => $item['location_name'],
            ];
        }

        return $this->ok(['week_start' => $weekStart->format('Y-m-d'), 'days' => $days]);
    }

    #[Route('/appointments', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/appointments', tags: ['Scheduling'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Appointment list')])]
    public function appointments(): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $items = $user->getRole()->value === 'ROLE_SYSTEM_ADMIN'
            ? $this->appointmentRepository->findBy([], ['id' => 'DESC'])
            : $this->appointmentRepository->findBy(['bookedBy' => $user], ['id' => 'DESC']);

        return $this->ok(['items' => array_map(fn (Appointment $a) => $this->appointmentToArray($a), $items)]);
    }

    private function requireUser(): User|JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        return $user;
    }

    private function windowToArray(AvailabilityWindow $window): array
    {
        return [
            'id' => $window->getId(),
            'practitioner_id' => $window->getPractitioner()?->getId(),
            'org_unit_id' => $window->getOrgUnitId(),
            'weekday' => $window->getWeekday(),
            'start_time' => $window->getStartTime()->format('H:i:s'),
            'end_time' => $window->getEndTime()->format('H:i:s'),
            'slot_minutes' => $window->getSlotMinutes(),
        ];
    }

    private function slotToArray(AppointmentSlot $slot): array
    {
        return [
            'id' => $slot->getId(),
            'practitioner' => ['id' => $slot->getPractitioner()->getId(), 'full_name' => $slot->getPractitioner()->getFullName()],
            'location' => ['id' => $slot->getLocation()->getId(), 'name' => $slot->getLocation()->getName()],
            'start_at' => $slot->getStartAt()->format(DATE_ATOM),
            'end_at' => $slot->getEndAt()->format(DATE_ATOM),
            'capacity' => $slot->getCapacity(),
            'available_count' => $slot->getAvailableCount(),
            'status' => $slot->getStatus()->value,
        ];
    }

    private function appointmentToArray(Appointment $appointment): array
    {
        return [
            'id' => $appointment->getId(),
            'practitioner' => ['id' => $appointment->getPractitioner()->getId(), 'full_name' => $appointment->getPractitioner()->getFullName()],
            'location' => ['id' => $appointment->getLocation()->getId(), 'name' => $appointment->getLocation()->getName()],
            'slot_id' => $appointment->getSlot()?->getId(),
            'start_at' => $appointment->getSlot()?->getStartAt()->format(DATE_ATOM),
            'end_at' => $appointment->getSlot()?->getEndAt()->format(DATE_ATOM),
            'state' => $appointment->getState()->value,
            'reschedule_count' => $appointment->getRescheduleCount(),
            'held_until' => $appointment->getHeldUntil()?->format(DATE_ATOM),
            'booked_at' => $appointment->getBookedAt()?->format(DATE_ATOM),
            'cancelled_at' => $appointment->getCancelledAt()?->format(DATE_ATOM),
        ];
    }
}
