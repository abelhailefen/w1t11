<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class AuditLogService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function log(
        ?int $userId,
        string $actionType,
        ?string $entityType,
        ?int $entityId,
        mixed $oldValue,
        mixed $newValue,
        ?string $ipAddress
    ): void {
        try {
            $occurredAt = new \DateTimeImmutable();
            $this->connection->insert('audit_logs', [
                'occurred_at' => $occurredAt->format('Y-m-d H:i:s'),
                'user_id' => $userId,
                'action_type' => $actionType,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'old_value_json' => $oldValue === null ? null : json_encode($oldValue, JSON_THROW_ON_ERROR),
                'new_value_json' => $newValue === null ? null : json_encode($newValue, JSON_THROW_ON_ERROR),
                'ip_address' => $ipAddress,
                'retention_expires_at' => $occurredAt->add(new \DateInterval('P7Y'))->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
        }
    }
}
