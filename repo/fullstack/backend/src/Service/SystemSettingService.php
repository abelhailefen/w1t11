<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class SystemSettingService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function all(): array
    {
        $rows = $this->connection->fetchAllAssociative('SELECT setting_key, setting_value FROM system_settings');
        $settings = [];
        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }

        return $settings;
    }

    public function bulkUpdate(array $settings, ?int $updatedBy): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        foreach ($settings as $key => $value) {
            $existing = $this->connection->fetchOne('SELECT id FROM system_settings WHERE setting_key = :k', ['k' => $key]);
            if ($existing !== false) {
                $this->connection->update('system_settings', [
                    'setting_value' => (string) $value,
                    'updated_by' => $updatedBy,
                    'updated_at' => $now,
                ], ['setting_key' => $key]);
            } else {
                $this->connection->insert('system_settings', [
                    'setting_key' => $key,
                    'setting_value' => (string) $value,
                    'updated_by' => $updatedBy,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function getInt(string $key, int $fallback): int
    {
        $value = $this->connection->fetchOne('SELECT setting_value FROM system_settings WHERE setting_key = :k', ['k' => $key]);
        if ($value === false || $value === null || $value === '') {
            return $fallback;
        }

        return (int) $value;
    }
}
