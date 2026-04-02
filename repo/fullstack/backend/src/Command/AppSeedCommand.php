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
            'login_lockout_attempts' => '5',
            'login_lockout_duration_minutes' => '15',
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
        $this->seedCredentialWorkflows();
        $this->seedSchedulingData();
        $this->seedQuestionBankData();

        $this->connection->insert('audit_logs', [
            'occurred_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'user_id' => null,
            'action_type' => 'CREATE',
            'entity_type' => 'SeedData',
            'entity_id' => null,
            'old_value_json' => null,
            'new_value_json' => json_encode(['source' => 'app:seed:initial'], JSON_THROW_ON_ERROR),
            'ip_address' => null,
            'retention_expires_at' => (new \DateTimeImmutable('+7 years'))->format('Y-m-d H:i:s'),
        ]);

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

    private function seedCredentialWorkflows(): void
    {
        $adminId = (int) $this->connection->fetchOne('SELECT id FROM users WHERE username = :u', ['u' => 'admin']);
        $reviewerId = (int) $this->connection->fetchOne('SELECT id FROM users WHERE username = :u', ['u' => 'reviewer']);
        $userId = (int) $this->connection->fetchOne('SELECT id FROM users WHERE username = :u', ['u' => 'user']);

        $scenarios = [
            ['Jordan Blake', 'DRAFT', [
                ['state' => 'DRAFT', 'by' => $userId, 'comment' => null],
                ['state' => 'DRAFT', 'by' => $userId, 'comment' => null],
            ]],
            ['Casey Morgan', 'SUBMITTED', [
                ['state' => 'DRAFT', 'by' => $userId, 'comment' => null],
                ['state' => 'SUBMITTED', 'by' => $userId, 'comment' => null],
            ]],
            ['Taylor Quinn', 'APPROVED', [
                ['state' => 'DRAFT', 'by' => $userId, 'comment' => null],
                ['state' => 'SUBMITTED', 'by' => $userId, 'comment' => null],
                ['state' => 'UNDER_REVIEW', 'by' => $reviewerId, 'comment' => null],
                ['state' => 'APPROVED', 'by' => $reviewerId, 'comment' => null],
            ]],
            ['Avery Brooks', 'REJECTED', [
                ['state' => 'DRAFT', 'by' => $userId, 'comment' => null],
                ['state' => 'SUBMITTED', 'by' => $userId, 'comment' => null],
                ['state' => 'UNDER_REVIEW', 'by' => $reviewerId, 'comment' => null],
                ['state' => 'REJECTED', 'by' => $reviewerId, 'comment' => 'Missing supporting record'],
            ]],
        ];

        foreach ($scenarios as [$name, $currentState, $versions]) {
            $practitionerId = (int) $this->connection->fetchOne('SELECT id FROM practitioners WHERE full_name = :n', ['n' => $name]);
            if ($practitionerId <= 0) {
                continue;
            }

            $existingSubmissionId = $this->connection->fetchOne(
                'SELECT id FROM credential_submissions WHERE practitioner_id = :pid ORDER BY id ASC LIMIT 1',
                ['pid' => $practitionerId]
            );

            if ($existingSubmissionId) {
                continue;
            }

            $now = new \DateTimeImmutable();
            $this->connection->insert('credential_submissions', [
                'practitioner_id' => $practitionerId,
                'current_state' => $currentState,
                'created_by_id' => $adminId,
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);

            $submissionId = (int) $this->connection->lastInsertId();
            $versionNo = 1;
            foreach ($versions as $version) {
                $this->connection->insert('credential_versions', [
                    'submission_id' => $submissionId,
                    'version_no' => $versionNo,
                    'payload_json' => json_encode(['seed' => true, 'version' => $versionNo], JSON_THROW_ON_ERROR),
                    'state' => $version['state'],
                    'rejection_comment' => $version['comment'],
                    'created_by_id' => $version['by'],
                    'created_at' => $now->modify(sprintf('+%d minutes', $versionNo))->format('Y-m-d H:i:s'),
                ]);
                $versionNo++;
            }
        }
    }

    private function seedSchedulingData(): void
    {
        $locationSeeds = [
            ['Main Hearing Center', '21 Judiciary Rd', 12],
            ['Downtown Annex', '88 Market St', 8],
            ['North Campus', '77 Northline Ave', 10],
        ];
        foreach ($locationSeeds as [$name, $address, $capacity]) {
            if (!$this->connection->fetchOne('SELECT id FROM locations WHERE name = :name', ['name' => $name])) {
                $this->connection->insert('locations', [
                    'name' => $name,
                    'address' => $address,
                    'capacity' => $capacity,
                    'status' => 'ACTIVE',
                    'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                ]);
            }
        }

        $practitionerIds = $this->connection->fetchFirstColumn('SELECT id FROM practitioners ORDER BY id ASC LIMIT 3');
        foreach ($practitionerIds as $pid) {
            for ($w = 1; $w <= 3; $w++) {
                $exists = $this->connection->fetchOne(
                    'SELECT id FROM availability_windows WHERE practitioner_id = :pid AND weekday = :weekday AND start_time = :start',
                    ['pid' => (int) $pid, 'weekday' => $w, 'start' => '09:00:00']
                );
                if ($exists) {
                    continue;
                }
                $this->connection->insert('availability_windows', [
                    'practitioner_id' => (int) $pid,
                    'org_unit_id' => null,
                    'weekday' => $w,
                    'start_time' => '09:00:00',
                    'end_time' => '12:00:00',
                    'slot_minutes' => 30,
                ]);
            }
        }

        $locationId = (int) $this->connection->fetchOne('SELECT id FROM locations ORDER BY id ASC LIMIT 1');
        $to = new \DateTimeImmutable('+30 days');
        foreach ($practitionerIds as $pid) {
            $cursor = new \DateTimeImmutable('today');
            while ($cursor <= $to) {
                $weekday = (int) $cursor->format('w');
                if (in_array($weekday, [1, 2, 3], true)) {
                    $slotStart = new \DateTimeImmutable($cursor->format('Y-m-d') . ' 09:00:00');
                    for ($i = 0; $i < 6; $i++) {
                        $slotEnd = $slotStart->modify('+30 minutes');
                        $exists = $this->connection->fetchOne(
                            'SELECT id FROM appointment_slots WHERE practitioner_id = :pid AND location_id = :lid AND start_at = :start AND end_at = :end',
                            [
                                'pid' => (int) $pid,
                                'lid' => $locationId,
                                'start' => $slotStart->format('Y-m-d H:i:s'),
                                'end' => $slotEnd->format('Y-m-d H:i:s'),
                            ]
                        );
                        if (!$exists) {
                            $this->connection->executeStatement(
                                'INSERT IGNORE INTO appointment_slots (practitioner_id, location_id, start_at, end_at, capacity, available_count, status) VALUES (:pid, :lid, :start_at, :end_at, :capacity, :available_count, :status)',
                                [
                                    'pid' => (int) $pid,
                                    'lid' => $locationId,
                                    'start_at' => $slotStart->format('Y-m-d H:i:s'),
                                    'end_at' => $slotEnd->format('Y-m-d H:i:s'),
                                    'capacity' => 1,
                                    'available_count' => 1,
                                    'status' => 'AVAILABLE',
                                ]
                            );
                        }
                        $slotStart = $slotEnd;
                    }
                }
                $cursor = $cursor->modify('+1 day');
            }
        }

        $adminId = (int) $this->connection->fetchOne('SELECT id FROM users WHERE username = :u', ['u' => 'admin']);
        $userId = (int) $this->connection->fetchOne('SELECT id FROM users WHERE username = :u', ['u' => 'user']);
        $sampleSlots = $this->connection->fetchAllAssociative('SELECT id, practitioner_id, location_id FROM appointment_slots ORDER BY start_at ASC LIMIT 10');
        foreach ($sampleSlots as $idx => $slot) {
            $exists = $this->connection->fetchOne('SELECT id FROM appointments WHERE slot_id = :sid', ['sid' => (int) $slot['id']]);
            if ($exists) {
                continue;
            }
            $state = $idx < 4 ? 'CONFIRMED' : ($idx < 7 ? 'HELD' : 'CANCELLED');
            $bookedBy = $idx % 2 === 0 ? $userId : $adminId;
            $heldUntil = $state === 'HELD' ? (new \DateTimeImmutable('+5 minutes'))->format('Y-m-d H:i:s') : null;
            $cancelledAt = $state === 'CANCELLED' ? (new \DateTimeImmutable())->format('Y-m-d H:i:s') : null;

            $this->connection->insert('appointments', [
                'practitioner_id' => (int) $slot['practitioner_id'],
                'location_id' => (int) $slot['location_id'],
                'slot_id' => (int) $slot['id'],
                'booked_by' => $bookedBy,
                'state' => $state,
                'held_until' => $heldUntil,
                'reschedule_count' => 0,
                'booked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'cancelled_at' => $cancelledAt,
            ]);

            if ($state === 'CONFIRMED') {
                $this->connection->executeStatement('UPDATE appointment_slots SET available_count = GREATEST(available_count - 1, 0), status = CASE WHEN available_count - 1 <= 0 THEN :full ELSE status END WHERE id = :id', [
                    'id' => (int) $slot['id'],
                    'full' => 'FULL',
                ]);
            }
        }
    }

    private function seedQuestionBankData(): void
    {
        $categories = [
            ['Regulatory Fundamentals', 'Foundational topics'],
            ['Licensing', 'License and registration'],
            ['Ethics', 'Ethics and conduct'],
            ['Client Protection', 'Client protection'],
            ['Reporting', 'Reporting and filings'],
        ];
        foreach ($categories as [$name, $desc]) {
            $this->connection->executeStatement('INSERT IGNORE INTO question_categories (name, description, parent_id, created_at) VALUES (:name, :description, :parent_id, :created_at)', [
                'name' => $name,
                'description' => $desc,
                'parent_id' => null,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        $tags = ['compliance', 'audit', 'ethics', 'risk', 'licensing', 'privacy', 'fraud', 'client', 'reporting', 'controls'];
        foreach ($tags as $tag) {
            $this->connection->executeStatement('INSERT IGNORE INTO question_tags (name, created_at) VALUES (:name, :created_at)', [
                'name' => $tag,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
        }

        $contentAdminId = (int) $this->connection->fetchOne('SELECT id FROM users WHERE username = :u', ['u' => 'content_admin']);
        $categoryIds = $this->connection->fetchFirstColumn('SELECT id FROM question_categories ORDER BY id ASC LIMIT 5');
        for ($i = 1; $i <= 20; $i++) {
            $marker = 'Seed question #' . $i;
            $existing = $this->connection->fetchOne('SELECT q.id FROM questions q INNER JOIN question_versions qv ON q.current_version_id = qv.id WHERE qv.content_html LIKE :m LIMIT 1', ['m' => '%' . $marker . '%']);
            if ($existing) {
                continue;
            }

            $categoryId = (int) $categoryIds[$i % max(1, count($categoryIds))];
            $status = $i % 3 === 0 ? 'PUBLISHED' : ($i % 5 === 0 ? 'OFFLINE' : 'DRAFT');
            $now = new \DateTimeImmutable();
            $this->connection->insert('questions', [
                'category_id' => $categoryId,
                'status' => $status,
                'current_version_id' => null,
                'created_by' => $contentAdminId,
                'duplicate_acknowledged' => 0,
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);
            $qid = (int) $this->connection->lastInsertId();
            $versions = $i <= 3 ? 3 : 1;
            for ($v = 1; $v <= $versions; $v++) {
                $html = sprintf('<p>%s version %d: Describe control objective and expected evidence.</p>', $marker, $v);
                $this->connection->insert('question_versions', [
                    'question_id' => $qid,
                    'version_no' => $v,
                    'content_html' => $html,
                    'plain_text_index' => mb_strtolower(strip_tags($html)),
                    'difficulty' => (($i + $v) % 5) + 1,
                    'metadata_json' => json_encode(['seed' => true, 'v' => $v], JSON_THROW_ON_ERROR),
                    'created_by' => $contentAdminId,
                    'created_at' => $now->modify(sprintf('+%d minutes', $v))->format('Y-m-d H:i:s'),
                ]);
                $vid = (int) $this->connection->lastInsertId();
                $this->connection->update('questions', ['current_version_id' => $vid], ['id' => $qid]);
            }

            $tagIds = $this->connection->fetchFirstColumn('SELECT id FROM question_tags ORDER BY id ASC LIMIT 2 OFFSET ' . ($i % 5));
            foreach ($tagIds as $tagId) {
                $this->connection->executeStatement('INSERT IGNORE INTO question_tag_map (question_id, tag_id) VALUES (:q, :t)', ['q' => $qid, 't' => (int) $tagId]);
            }
        }
    }
}
