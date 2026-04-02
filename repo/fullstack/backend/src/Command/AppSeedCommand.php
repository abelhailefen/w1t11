<?php

namespace App\Command;

use App\Service\EncryptionService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:seed:initial', description: 'Seeds initial idempotent data')]
class AppSeedCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EncryptionService $encryptionService
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = [
            ['admin', 'Admin@123', 'ROLE_SYSTEM_ADMIN'],
            ['user', 'User@123', 'ROLE_USER'],
            ['content_admin', 'Content@123', 'ROLE_CONTENT_ADMIN'],
            ['reviewer', 'Reviewer@123', 'ROLE_CREDENTIAL_REVIEWER'],
            ['analyst', 'Analyst@123', 'ROLE_ANALYST'],
        ];

        foreach ($users as [$username, $password, $role]) {
            $existing = $this->connection->fetchOne('SELECT id FROM users WHERE username = :username', ['username' => $username]);
            if ($existing) {
                continue;
            }

            $this->connection->insert('users', [
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
                'role' => $role,
                'status' => 'ACTIVE',
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        $settings = [
            'appointment_slot_minutes' => '30',
            'appointment_hold_minutes' => '5',
            'duplicate_similarity_threshold' => '80',
            'alert_rejection_threshold' => '5',
            'alert_rejection_window_hours' => '24',
        ];

        foreach ($settings as $key => $value) {
            $existing = $this->connection->fetchOne('SELECT id FROM system_settings WHERE setting_key = :k', ['k' => $key]);
            if (!$existing) {
                $this->connection->insert('system_settings', [
                    'setting_key' => $key,
                    'setting_value' => $value,
                    'updated_by' => null,
                    'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);
            }
        }

        $this->seedReferenceData();
        $this->seedSampleDomainData();

        $output->writeln('Initial seed complete.');
        return Command::SUCCESS;
    }

    private function seedReferenceData(): void
    {
        $firms = [
            ['Eagle Point Legal', '100 Compliance Ave'],
            ['Summit Regulatory Group', '42 Oversight Blvd'],
            ['North Harbor Compliance', '55 Harbor Road'],
            ['Blue River Advisory', '210 River Street'],
            ['Meridian Counsel Partners', '11 Meridian Plaza'],
        ];

        foreach ($firms as [$name, $address]) {
            if (!$this->connection->fetchOne('SELECT id FROM firms WHERE name = :name', ['name' => $name])) {
                $this->connection->insert('firms', [
                    'name' => $name,
                    'address' => $address,
                    'status' => 'ACTIVE',
                    'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);
            }
        }

        if (!$this->connection->fetchOne('SELECT id FROM locations WHERE name = :name', ['name' => 'Main Hearing Center'])) {
            $this->connection->insert('locations', [
                'name' => 'Main Hearing Center',
                'address' => '21 Judiciary Rd',
                'capacity' => 12,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        if (!$this->connection->fetchOne('SELECT id FROM org_units WHERE name = :name', ['name' => 'Central Operations'])) {
            $this->connection->insert('org_units', [
                'name' => 'Central Operations',
                'parent_id' => null,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        if (!$this->connection->fetchOne('SELECT id FROM question_categories WHERE name = :name', ['name' => 'Regulatory Fundamentals'])) {
            $this->connection->insert('question_categories', [
                'name' => 'Regulatory Fundamentals',
                'description' => 'Foundational compliance questions',
                'parent_id' => null,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }

    private function seedSampleDomainData(): void
    {
        $firmId = (int) $this->connection->fetchOne('SELECT id FROM firms WHERE name = :name', ['name' => 'Eagle Point Legal']);
        $locationId = (int) $this->connection->fetchOne('SELECT id FROM locations WHERE name = :name', ['name' => 'Main Hearing Center']);
        $categoryId = (int) $this->connection->fetchOne('SELECT id FROM question_categories WHERE name = :name', ['name' => 'Regulatory Fundamentals']);
        $adminId = (int) $this->connection->fetchOne('SELECT id FROM users WHERE username = :username', ['username' => 'admin']);

        $practitioners = [
            ['Jordan Blake', 'Eagle Point Legal', 'NY-445566', 'NY', 'jordan.blake@example.local', '+1-555-0101'],
            ['Casey Morgan', 'Summit Regulatory Group', 'CA-102938', 'CA', 'casey.morgan@example.local', '+1-555-0102'],
            ['Taylor Quinn', 'North Harbor Compliance', 'TX-556677', 'TX', 'taylor.quinn@example.local', '+1-555-0103'],
            ['Avery Brooks', 'Blue River Advisory', 'FL-889900', 'FL', 'avery.brooks@example.local', '+1-555-0104'],
            ['Riley Stone', 'Meridian Counsel Partners', 'IL-112233', 'IL', 'riley.stone@example.local', '+1-555-0105'],
            ['Morgan Lee', 'Eagle Point Legal', 'WA-665544', 'WA', 'morgan.lee@example.local', '+1-555-0106'],
        ];

        foreach ($practitioners as [$fullName, $firmName, $license, $jurisdiction, $email, $phone]) {
            $existing = $this->connection->fetchOne('SELECT id FROM practitioners WHERE full_name = :name', ['name' => $fullName]);
            if ($existing) {
                continue;
            }

            $pFirmId = (int) $this->connection->fetchOne('SELECT id FROM firms WHERE name = :name', ['name' => $firmName]);
            $this->connection->insert('practitioners', [
                'firm_id' => $pFirmId,
                'full_name' => $fullName,
                'license_number_encrypted' => $this->encryptionService->encrypt($license),
                'license_jurisdiction' => $jurisdiction,
                'contact_email' => $email,
                'contact_phone' => $phone,
                'status' => 'ACTIVE',
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        $practitionerId = (int) $this->connection->fetchOne('SELECT id FROM practitioners WHERE full_name = :name', ['name' => 'Jordan Blake']);

        if (!$this->connection->fetchOne('SELECT id FROM appointments WHERE practitioner_id = :pid LIMIT 1', ['pid' => $practitionerId])) {
            $this->connection->insert('appointments', [
                'practitioner_id' => $practitionerId,
                'location_id' => $locationId,
                'slot_id' => null,
                'booked_by' => $adminId,
                'state' => 'HELD',
                'held_until' => (new \DateTimeImmutable('+5 minutes'))->format('Y-m-d H:i:s'),
                'reschedule_count' => 0,
                'booked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'cancelled_at' => null,
            ]);
        }

        if (!$this->connection->fetchOne('SELECT id FROM questions WHERE category_id = :cid LIMIT 1', ['cid' => $categoryId])) {
            $this->connection->insert('questions', [
                'category_id' => $categoryId,
                'status' => 'DRAFT',
                'created_by' => $adminId,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }
    }
}
