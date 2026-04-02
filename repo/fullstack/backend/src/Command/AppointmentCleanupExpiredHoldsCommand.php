<?php

namespace App\Command;

use App\Service\BookingService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:appointments:cleanup-expired-holds', description: 'Cleanup expired held appointments')]
class AppointmentCleanupExpiredHoldsCommand extends Command
{
    public function __construct(private readonly BookingService $bookingService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->bookingService->cleanupExpiredHolds();
        $output->writeln(sprintf('Expired holds cleaned: %d', $count));
        return Command::SUCCESS;
    }
}
