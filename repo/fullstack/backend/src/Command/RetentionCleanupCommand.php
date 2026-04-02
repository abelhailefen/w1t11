<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:audit:cleanup', description: 'Cleanup expired audit records')]
class RetentionCleanupCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Actually delete expired records');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $count = (int) $this->connection->fetchOne('SELECT COUNT(id) FROM audit_logs WHERE retention_expires_at IS NOT NULL AND retention_expires_at < :now', [
            'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        if (!$input->getOption('force')) {
            $io->writeln(sprintf('Dry-run: %d records eligible for deletion', $count));
            return Command::SUCCESS;
        }

        $deleted = $this->connection->executeStatement('DELETE FROM audit_logs WHERE retention_expires_at IS NOT NULL AND retention_expires_at < :now', [
            'now' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        $io->success(sprintf('Deleted %d expired audit records', $deleted));
        return Command::SUCCESS;
    }
}
