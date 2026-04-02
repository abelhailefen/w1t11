<?php

namespace App\Tests\Unit;

use App\Service\ApiException;
use App\Service\BookingService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class BookingConflictTest extends KernelTestCase
{
    public function testSecondBookingForOverlappingWindowFails(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        /** @var Connection $db */
        $db = $c->get(Connection::class);
        /** @var BookingService $service */
        $service = $c->get(BookingService::class);
        $userId = (int) $db->fetchOne("SELECT id FROM users WHERE username='user'");
        $pid = (int) $db->fetchOne('SELECT id FROM practitioners ORDER BY id ASC LIMIT 1');
        $lid = (int) $db->fetchOne("SELECT id FROM locations WHERE status='ACTIVE' ORDER BY id ASC LIMIT 1");
        $base = (new \DateTimeImmutable(sprintf('+2 days +%d minutes', random_int(1, 5000))))->setTime((int) date('H'), 0);
        $start = $base->format('Y-m-d H:i:s');
        $end = $base->modify('+30 minutes')->format('Y-m-d H:i:s');
        $overlapStart = $base->modify('+15 minutes')->format('Y-m-d H:i:s');
        $overlapEnd = $base->modify('+45 minutes')->format('Y-m-d H:i:s');

        $db->insert('appointment_slots', ['practitioner_id' => $pid, 'location_id' => $lid, 'start_at' => $overlapStart, 'end_at' => $overlapEnd, 'capacity' => 1, 'available_count' => 1, 'status' => 'AVAILABLE']);
        $slot1 = (int) $db->lastInsertId();
        $db->insert('appointment_slots', ['practitioner_id' => $pid, 'location_id' => $lid, 'start_at' => $start, 'end_at' => $end, 'capacity' => 1, 'available_count' => 1, 'status' => 'AVAILABLE']);
        $slot2 = (int) $db->lastInsertId();

        $appt = $service->holdSlot($slot1, $userId);
        $service->confirmBooking($appt, $userId);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Scheduling conflict detected');
        $service->holdSlot($slot2, $userId);
    }
}
