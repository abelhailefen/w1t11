<?php

namespace App\Tests\Unit;

use App\Service\ApiException;
use App\Service\BookingService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class HoldExpirationTest extends KernelTestCase
{
    public function testConfirmAfterHoldExpiredReturnsGone(): void
    {
        self::bootKernel();
        $db = static::getContainer()->get(Connection::class);
        $service = static::getContainer()->get(BookingService::class);
        $userId = (int) $db->fetchOne("SELECT id FROM users WHERE username='user'");
        $firmId = (int) $db->fetchOne('SELECT id FROM firms ORDER BY id ASC LIMIT 1');
        if ($firmId <= 0) {
            $db->insert('firms', ['name' => 'HoldTest Firm', 'address' => 'Test', 'status' => 'ACTIVE', 'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
            $firmId = (int) $db->lastInsertId();
        }
        $db->insert('practitioners', [
            'firm_id' => $firmId,
            'full_name' => 'Hold Test Practitioner ' . uniqid(),
            'license_number_encrypted' => 'hold-test-placeholder-license',
            'license_jurisdiction' => 'NY',
            'contact_email' => null,
            'contact_phone' => null,
            'status' => 'ACTIVE',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        $pid = (int) $db->lastInsertId();

        $db->insert('locations', ['name' => 'HoldTest-' . uniqid(), 'address' => 'Test', 'capacity' => 1, 'status' => 'ACTIVE', 'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
        $lid = (int) $db->lastInsertId();
        $start = (new \DateTimeImmutable(sprintf('+2 days +%d minutes', random_int(1, 4000))));
        $db->insert('appointment_slots', ['practitioner_id' => $pid, 'location_id' => $lid, 'start_at' => $start->format('Y-m-d H:i:s'), 'end_at' => $start->modify('+30 minutes')->format('Y-m-d H:i:s'), 'capacity' => 1, 'available_count' => 1, 'status' => 'AVAILABLE']);
        $slotId = (int) $db->lastInsertId();

        $appt = $service->holdSlot($slotId, $userId);
        $db->update('appointments', ['held_until' => (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s')], ['id' => $appt]);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Hold expired');
        $service->confirmBooking($appt, $userId);
    }
}
