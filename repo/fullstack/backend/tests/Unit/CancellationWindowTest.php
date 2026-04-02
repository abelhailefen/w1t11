<?php

namespace App\Tests\Unit;

use App\Service\ApiException;
use App\Service\BookingService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CancellationWindowTest extends KernelTestCase
{
    public function testCancelWithin24HoursUserDeniedAdminAllowed(): void
    {
        self::bootKernel();
        $db = static::getContainer()->get(Connection::class);
        $service = static::getContainer()->get(BookingService::class);
        $userId = (int) $db->fetchOne("SELECT id FROM users WHERE username='user'");
        $adminId = (int) $db->fetchOne("SELECT id FROM users WHERE username='admin'");
        $pid = (int) $db->fetchOne('SELECT id FROM practitioners ORDER BY id ASC LIMIT 1');
        $lid = (int) $db->fetchOne("SELECT id FROM locations WHERE status='ACTIVE' ORDER BY id ASC LIMIT 1");
        $start = new \DateTimeImmutable('+6 hours');
        $db->insert('appointment_slots', ['practitioner_id' => $pid, 'location_id' => $lid, 'start_at' => $start->format('Y-m-d H:i:s'), 'end_at' => $start->modify('+30 minutes')->format('Y-m-d H:i:s'), 'capacity' => 1, 'available_count' => 1, 'status' => 'AVAILABLE']);
        $slotId = (int) $db->lastInsertId();

        $appt = $service->holdSlot($slotId, $userId);
        $service->confirmBooking($appt, $userId);

        try {
            $service->cancel($appt, $userId);
            self::fail('Expected exception not thrown');
        } catch (ApiException $e) {
            self::assertSame(403, $e->getHttpCode());
        }

        $service->cancel($appt, $adminId);
        self::assertSame('CANCELLED', $db->fetchOne('SELECT state FROM appointments WHERE id = :id', ['id' => $appt]));
    }
}
