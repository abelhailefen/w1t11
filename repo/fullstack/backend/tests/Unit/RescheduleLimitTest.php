<?php

namespace App\Tests\Unit;

use App\Service\ApiException;
use App\Service\BookingService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RescheduleLimitTest extends KernelTestCase
{
    public function testThirdRescheduleRejected(): void
    {
        self::bootKernel();
        $c = static::getContainer();
        $db = $c->get(Connection::class);
        $service = $c->get(BookingService::class);
        $userId = (int) $db->fetchOne("SELECT id FROM users WHERE username='user'");
        $pid = (int) $db->fetchOne('SELECT id FROM practitioners ORDER BY id ASC LIMIT 1');
        $lid = (int) $db->fetchOne("SELECT id FROM locations WHERE status='ACTIVE' ORDER BY id ASC LIMIT 1");
        $base = new \DateTimeImmutable(sprintf('+3 days +%d minutes', random_int(1, 3000)));
        $slotIds = [];
        for ($i = 0; $i < 4; $i++) {
            $start = $base->modify(sprintf('+%d hours', $i));
            $end = $start->modify('+30 minutes');
            $db->insert('appointment_slots', ['practitioner_id' => $pid, 'location_id' => $lid, 'start_at' => $start->format('Y-m-d H:i:s'), 'end_at' => $end->format('Y-m-d H:i:s'), 'capacity' => 1, 'available_count' => 1, 'status' => 'AVAILABLE']);
            $slotIds[] = (int) $db->lastInsertId();
        }
        $appt = $service->holdSlot($slotIds[0], $userId);
        $service->confirmBooking($appt, $userId);
        $service->reschedule($appt, $slotIds[1], $userId);
        $service->reschedule($appt, $slotIds[2], $userId);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Reschedule limit reached');
        $service->reschedule($appt, $slotIds[3], $userId);
    }
}
