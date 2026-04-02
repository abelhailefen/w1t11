<?php

namespace App\Tests\Unit;

use App\Service\ApiException;
use App\Service\BookingService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AdvanceLimitTest extends KernelTestCase
{
    public function testAdvanceWindow(): void
    {
        self::bootKernel();
        $db = static::getContainer()->get(Connection::class);
        $service = static::getContainer()->get(BookingService::class);
        $userId = (int) $db->fetchOne("SELECT id FROM users WHERE username='user'");
        $pid = (int) $db->fetchOne('SELECT id FROM practitioners ORDER BY id ASC LIMIT 1');
        $db->insert('locations', ['name' => 'AdvanceTest-' . uniqid(), 'address' => 'Test', 'capacity' => 1, 'status' => 'ACTIVE', 'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
        $lid = (int) $db->lastInsertId();

        $minuteOffset = random_int(1, 1200);
        $start89 = new \DateTimeImmutable(sprintf('+89 days +%d minutes', $minuteOffset));
        $db->insert('appointment_slots', ['practitioner_id' => $pid, 'location_id' => $lid, 'start_at' => $start89->format('Y-m-d H:i:s'), 'end_at' => $start89->modify('+30 minutes')->format('Y-m-d H:i:s'), 'capacity' => 1, 'available_count' => 1, 'status' => 'AVAILABLE']);
        $slot89 = (int) $db->lastInsertId();
        $appt = $service->holdSlot($slot89, $userId);
        $service->confirmBooking($appt, $userId);
        self::assertSame('CONFIRMED', $db->fetchOne('SELECT state FROM appointments WHERE id = :id', ['id' => $appt]));

        $start91 = new \DateTimeImmutable(sprintf('+91 days +%d minutes', $minuteOffset));
        $db->insert('appointment_slots', ['practitioner_id' => $pid, 'location_id' => $lid, 'start_at' => $start91->format('Y-m-d H:i:s'), 'end_at' => $start91->modify('+30 minutes')->format('Y-m-d H:i:s'), 'capacity' => 1, 'available_count' => 1, 'status' => 'AVAILABLE']);
        $slot91 = (int) $db->lastInsertId();

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Cannot book slots more than 90 days ahead');
        $service->holdSlot($slot91, $userId);
    }
}
