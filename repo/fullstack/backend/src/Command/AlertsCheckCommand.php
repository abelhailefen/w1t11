<?php

namespace App\Command;

use App\Service\AlertService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:alerts:check', description: 'Run alert anomaly checks')]
class AlertsCheckCommand extends Command
{
    public function __construct(private readonly AlertService $alertService)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $result = $this->alertService->sweep();
        $io->success(sprintf('Alert sweep complete. Created: %d', (int) ($result['created'] ?? 0)));
        return Command::SUCCESS;
    }
}
