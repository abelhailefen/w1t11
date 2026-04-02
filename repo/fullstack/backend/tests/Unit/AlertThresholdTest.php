<?php

namespace App\Tests\Unit;

use App\Service\AlertService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AlertThresholdTest extends KernelTestCase
{
    public function testThresholdByFirm(): void
    {
        self::bootKernel();
        /** @var Connection $db */
        $db = static::getContainer()->get(Connection::class);
        /** @var AlertService $alerts */
        $alerts = static::getContainer()->get(AlertService::class);

        $db->executeStatement('DELETE FROM alerts');
        $db->executeStatement("DELETE FROM system_settings WHERE setting_key IN ('alert_rejection_threshold', 'alert_rejection_window_hours')");
        $db->insert('system_settings', [
            'setting_key' => 'alert_rejection_threshold',
            'setting_value' => '5',
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        $db->insert('system_settings', [
            'setting_key' => 'alert_rejection_window_hours',
            'setting_value' => '24',
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $db->insert('firms', ['name' => 'alert-firm-1-' . bin2hex(random_bytes(2)), 'address' => null, 'status' => 'ACTIVE', 'created_at' => $now]);
        $firm1 = (int) $db->lastInsertId();
        $db->insert('firms', ['name' => 'alert-firm-2-' . bin2hex(random_bytes(2)), 'address' => null, 'status' => 'ACTIVE', 'created_at' => $now]);
        $firm2 = (int) $db->lastInsertId();

        $createRejected = function (int $firmId) use ($db, $now): void {
            $db->insert('practitioners', [
                'firm_id' => $firmId,
                'full_name' => 'Alert Threshold Practitioner ' . bin2hex(random_bytes(3)),
                'license_number_encrypted' => 'X',
                'license_jurisdiction' => 'NA',
                'contact_email' => null,
                'contact_phone' => null,
                'status' => 'ACTIVE',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $pid = (int) $db->lastInsertId();
            $db->insert('credential_submissions', [
                'practitioner_id' => $pid,
                'current_state' => 'REJECTED',
                'created_by_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        };

        for ($i = 0; $i < 4; $i++) {
            $createRejected($firm1);
        }
        $alerts->checkRejectedCredentialsForFirm($firm1);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM alerts'));

        $createRejected($firm1);
        $alerts->checkRejectedCredentialsForFirm($firm1);
        self::assertGreaterThanOrEqual(1, (int) $db->fetchOne("SELECT COUNT(*) FROM alerts WHERE alert_type = 'REJECTED_CREDENTIAL_SPIKE'"));

        $db->executeStatement('DELETE FROM alerts');
        $db->executeStatement('DELETE FROM credential_submissions WHERE created_by_id = 1 AND current_state = :state AND updated_at >= :from', [
            'state' => 'REJECTED',
            'from' => (new \DateTimeImmutable('-1 minute'))->format('Y-m-d H:i:s'),
        ]);

        for ($i = 0; $i < 3; $i++) {
            $createRejected($firm1);
            $createRejected($firm2);
        }
        $alerts->checkRejectedCredentialsForFirm($firm1);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM alerts'));
    }
}
