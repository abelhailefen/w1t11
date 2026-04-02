<?php

namespace App\Tests\Unit;

use App\Command\RetentionCleanupCommand;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class RetentionCommandTest extends KernelTestCase
{
    public function testDryRunAndForceDeletion(): void
    {
        self::bootKernel();
        /** @var Connection $db */
        $db = static::getContainer()->get(Connection::class);
        $db->insert('audit_logs', [
            'occurred_at' => (new \DateTimeImmutable('-8 years'))->format('Y-m-d H:i:s'),
            'user_id' => null,
            'action_type' => 'TEST',
            'entity_type' => 'Test',
            'entity_id' => null,
            'old_value_json' => null,
            'new_value_json' => null,
            'ip_address' => null,
            'retention_expires_at' => (new \DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
        ]);

        $app = new Application(self::$kernel);
        $command = $app->find('app:audit:cleanup');
        $tester = new CommandTester($command);
        $tester->execute([]);
        self::assertStringContainsString('Dry-run', $tester->getDisplay());

        $tester->execute(['--force' => true]);
        self::assertStringContainsString('Deleted', $tester->getDisplay());
    }
}
