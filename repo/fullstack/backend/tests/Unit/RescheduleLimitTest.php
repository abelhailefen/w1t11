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
        $firmId = (int) $db->fetchOne('SELECT id FROM firms ORDER BY id ASC LIMIT 1');
        if ($firmId <= 0) {
            $db->insert('firms', ['name' => 'RescheduleTest Firm', 'address' => 'Test', 'status' => 'ACTIVE', 'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
            $firmId = (int) $db->lastInsertId();
        }
        $db->insert('practitioners', [
            'firm_id' => $firmId,
            'full_name' => 'Reschedule Test Practitioner ' . uniqid(),
            'license_number_encrypted' => 'reschedule-test-placeholder-license',
            'license_jurisdiction' => 'NY',
            'contact_email' => null,
            'contact_phone' => null,
            'status' => 'ACTIVE',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        $pid = (int) $db->lastInsertId();

        $db->insert('locations', ['name' => 'RescheduleTest-' . uniqid(), 'address' => 'Test', 'capacity' => 1, 'status' => 'ACTIVE', 'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
        $lid = (int) $db->lastInsertId();
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
