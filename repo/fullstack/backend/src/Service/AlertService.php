<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class AlertService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly SystemSettingService $systemSettingService
    )
    {
    }

    public function checkRejectedCredentialsForFirm(int $firmId): void
    {
        $windowHours = max(1, $this->systemSettingService->getInt('alert_rejection_window_hours', (int) ($_ENV['ALERT_REJECTION_WINDOW_HOURS'] ?? $_SERVER['ALERT_REJECTION_WINDOW_HOURS'] ?? 24)));
        $threshold = max(1, $this->systemSettingService->getInt('alert_rejection_threshold', (int) ($_ENV['ALERT_REJECTION_THRESHOLD'] ?? $_SERVER['ALERT_REJECTION_THRESHOLD'] ?? 5)));

        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(cs.id) FROM credential_submissions cs INNER JOIN practitioners p ON p.id = cs.practitioner_id WHERE p.firm_id = :firmId AND cs.current_state = :state AND cs.updated_at >= :from',
            ['firmId' => $firmId, 'state' => 'REJECTED', 'from' => (new \DateTimeImmutable(sprintf('-%d hours', $windowHours)))->format('Y-m-d H:i:s')]
        );

        if ($count >= $threshold) {
            $this->createDeduplicated('REJECTED_CREDENTIAL_SPIKE', 'HIGH', 'Rejected credentials exceeded threshold for firm', ['firm_id' => $firmId, 'rejections_24h' => $count]);
        }
    }

    public function checkFailedLoginsForUsername(string $username): void
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(id) FROM login_attempts WHERE username = :username AND success = 0 AND attempted_at >= :from',
            ['username' => $username, 'from' => (new \DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s')]
        );
        if ($count > 10) {
            $this->createDeduplicated('FAILED_LOGIN_SPIKE', 'MEDIUM', 'Failed logins exceeded threshold for username', ['username' => $username, 'failed_logins_1h' => $count]);
        }
    }

    public function checkLicenseRevealForUser(int $userId): void
    {
        $count = (int) $this->connection->fetchOne(
            'SELECT COUNT(id) FROM sensitive_access_logs WHERE user_id = :userId AND accessed_at >= :from',
            ['userId' => $userId, 'from' => (new \DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s')]
        );
        if ($count > 50) {
            $this->createDeduplicated('LICENSE_REVEAL_SPIKE', 'HIGH', 'License reveals exceeded threshold for user', ['user_id' => $userId, 'license_reveals_1h' => $count]);
        }
    }

    public function acknowledge(int $alertId, int $userId): bool
    {
        $affected = $this->connection->update('alerts', [
            'acknowledged_by' => $userId,
            'acknowledged_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $alertId]);

        return $affected > 0;
    }

    public function createCriticalAlert(string $type, string $message, array $context = []): void
    {
        $this->createDeduplicated($type, 'CRITICAL', $message, $context);
    }

    public function sweep(): array
    {
        $created = 0;
        $firms = $this->connection->fetchFirstColumn('SELECT DISTINCT p.firm_id FROM credential_submissions cs INNER JOIN practitioners p ON p.id = cs.practitioner_id WHERE cs.updated_at >= :from', ['from' => (new \DateTimeImmutable('-24 hours'))->format('Y-m-d H:i:s')]);
        foreach ($firms as $firmId) {
            $before = $this->countUnacked();
            $this->checkRejectedCredentialsForFirm((int) $firmId);
            $created += max(0, $this->countUnacked() - $before);
        }

        $usernames = $this->connection->fetchFirstColumn('SELECT DISTINCT username FROM login_attempts WHERE attempted_at >= :from', ['from' => (new \DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s')]);
        foreach ($usernames as $username) {
            $before = $this->countUnacked();
            $this->checkFailedLoginsForUsername((string) $username);
            $created += max(0, $this->countUnacked() - $before);
        }

        $users = $this->connection->fetchFirstColumn('SELECT DISTINCT user_id FROM sensitive_access_logs WHERE accessed_at >= :from AND user_id IS NOT NULL', ['from' => (new \DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s')]);
        foreach ($users as $userId) {
            $before = $this->countUnacked();
            $this->checkLicenseRevealForUser((int) $userId);
            $created += max(0, $this->countUnacked() - $before);
        }

        return ['created' => $created];
    }

    private function createDeduplicated(string $type, string $severity, string $message, array $context): void
    {
        $contextJson = json_encode($context, JSON_THROW_ON_ERROR);
        $exists = (int) $this->connection->fetchOne(
            'SELECT COUNT(id) FROM alerts WHERE alert_type = :type AND severity = :severity AND message = :message AND context_json = :context AND acknowledged_at IS NULL',
            ['type' => $type, 'severity' => $severity, 'message' => $message, 'context' => $contextJson]
        );
        if ($exists > 0) {
            return;
        }

        $this->connection->insert('alerts', [
            'alert_type' => $type,
            'severity' => $severity,
            'message' => $message,
            'context_json' => $contextJson,
            'triggered_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'acknowledged_by' => null,
            'acknowledged_at' => null,
        ]);
    }

    private function countUnacked(): int
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(id) FROM alerts WHERE acknowledged_at IS NULL');
    }
}
