<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class AnomalyTriggerTest extends WebTestCase
{
    public function testRejectionsTriggerHighAlert(): void
    {
        self::bootKernel();
        /** @var Connection $db */
        $db = static::getContainer()->get(Connection::class);
        $db->executeStatement('DELETE FROM alerts');

        for ($i = 1; $i <= 6; $i++) {
            $db->executeStatement('UPDATE credential_submissions SET current_state = :state, updated_at = :updatedAt WHERE id = :id', [
                'state' => 'REJECTED',
                'updatedAt' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'id' => $i,
            ]);
            $db->executeStatement('UPDATE practitioners SET firm_id = 1 WHERE id = (SELECT practitioner_id FROM credential_submissions WHERE id = :id)', ['id' => $i]);
        }

        $app = new Application(self::$kernel);
        $command = $app->find('app:alerts:check');
        $tester = new CommandTester($command);
        $tester->execute([]);

        $count = (int) $db->fetchOne("SELECT COUNT(*) FROM alerts WHERE alert_type = 'REJECTED_CREDENTIAL_SPIKE' AND severity = 'HIGH'");

        self::assertGreaterThanOrEqual(1, $count);
    }
}
