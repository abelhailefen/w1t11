<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\NativePasswordHasher;

#[AsCommand(name: 'app:seed:initial', description: 'Seeds initial idempotent data')]
class AppSeedCommand extends Command
{
    public function __construct(private readonly Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $hasher = new NativePasswordHasher(12);
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
                'password_hash' => $hasher->hash($password),
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
        if (!$this->connection->fetchOne('SELECT id FROM firms WHERE name = :name', ['name' => 'Eagle Point Legal'])) {
            $this->connection->insert('firms', [
                'name' => 'Eagle Point Legal',
                'address' => '100 Compliance Ave',
                'status' => 'ACTIVE',
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
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

        if (!$this->connection->fetchOne('SELECT id FROM practitioners WHERE full_name = :name', ['name' => 'Jordan Blake'])) {
            $this->connection->insert('practitioners', [
                'firm_id' => $firmId,
                'full_name' => 'Jordan Blake',
                'license_number_encrypted' => 'seed-encrypted-license-placeholder',
                'license_jurisdiction' => 'NY',
                'contact_email' => 'jordan.blake@example.local',
                'contact_phone' => '+1-555-0101',
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
