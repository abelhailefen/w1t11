<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use App\Tests\Api\ApiWebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AnomalyTriggerTest extends ApiWebTestCase
{
    public function testRejectionsTriggerHighAlert(): void
    {
        self::bootKernel();
        /** @var Connection $db */
        $db = static::getContainer()->get(Connection::class);
        $db->executeStatement('DELETE FROM alerts');
        $db->executeStatement("UPDATE system_settings SET setting_value = '1' WHERE setting_key = 'alert_rejection_threshold'");
        $db->executeStatement("UPDATE system_settings SET setting_value = '24' WHERE setting_key = 'alert_rejection_window_hours'");

        $submissionId = (int) $db->fetchOne('SELECT id FROM credential_submissions ORDER BY id ASC LIMIT 1');
        if ($submissionId > 0) {
            $db->executeStatement('UPDATE practitioners SET firm_id = 1 WHERE id = (SELECT practitioner_id FROM credential_submissions WHERE id = :id)', ['id' => $submissionId]);
            $db->executeStatement('UPDATE credential_submissions SET current_state = :state, updated_at = :updatedAt WHERE id = :id', [
                'state' => 'REJECTED',
                'updatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'id' => $submissionId,
            ]);
        }

        $app = new Application(self::$kernel);
        $command = $app->find('app:alerts:check');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $count = (int) $db->fetchOne("SELECT COUNT(*) FROM alerts WHERE alert_type = 'REJECTED_CREDENTIAL_SPIKE' AND severity = 'HIGH'");

        self::assertGreaterThanOrEqual(1, $count);
    }
}
