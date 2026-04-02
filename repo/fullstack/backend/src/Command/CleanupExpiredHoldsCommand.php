<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:appointments:cleanup-expired-holds', description: 'Releases expired held appointments')]
class CleanupExpiredHoldsCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $affected = $this->connection->executeStatement(
            "UPDATE appointments SET state = 'EXPIRED' WHERE state = 'HELD' AND held_until IS NOT NULL AND held_until < NOW()"
        );

        $output->writeln(sprintf('Expired holds cleaned: %d', $affected));
        return Command::SUCCESS;
    }
}
