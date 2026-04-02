<?php

namespace App\Command;

use App\Service\EncryptionService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:migrate:fix-license-encryption', description: 'Re-encrypts legacy base64 license values')]
class MigrateFixLicenseEncryptionCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EncryptionService $encryptionService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->connection->fetchAllAssociative('SELECT id, license_number_encrypted FROM practitioners');
        $updated = 0;

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $value = (string) $row['license_number_encrypted'];

            if ($value === '') {
                continue;
            }

            try {
                $this->encryptionService->decrypt($value);
                continue;
            } catch (\Throwable) {
            }

            $decoded = base64_decode($value, true);
            if ($decoded === false || trim($decoded) === '') {
                continue;
            }

            $this->connection->update('practitioners', [
                'license_number_encrypted' => $this->encryptionService->encrypt($decoded),
            ], ['id' => $id]);
            $updated++;
        }

        $output->writeln(sprintf('License encryption migration complete. Updated rows: %d', $updated));

        return Command::SUCCESS;
    }
}
