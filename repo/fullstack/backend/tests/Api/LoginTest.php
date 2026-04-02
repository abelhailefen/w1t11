<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use App\Tests\Api\ApiWebTestCase;

class LoginTest extends ApiWebTestCase
{
    public function testLoginAndLockoutFlow(): void
    {
        $client = static::createClient();
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $username = 'captcha_' . bin2hex(random_bytes(4));
        $password = 'Strong@123';
        $connection->executeStatement("UPDATE system_settings SET setting_value = '5' WHERE setting_key = 'login_lockout_attempts'");
        $connection->executeStatement("UPDATE system_settings SET setting_value = '15' WHERE setting_key = 'login_lockout_duration_minutes'");

        $client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
            'full_name' => 'Captcha Tester',
            'firm_affiliation' => 'QA Firm',
            'license_number' => 'QA-1001',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $connection->executeStatement('DELETE FROM login_attempts WHERE username = :username', ['username' => $username]);
        $connection->executeStatement('DELETE FROM audit_logs WHERE action_type = :action', ['action' => 'LOGIN_FAILED']);

        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $data);

        for ($i = 0; $i < 4; $i++) {
            $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
                'username' => $username,
                'password' => 'wrong',
            ], JSON_THROW_ON_ERROR));
            self::assertResponseStatusCodeSame(401);
        }

        $failedAuditCount = (int) $connection->fetchOne('SELECT COUNT(*) FROM audit_logs WHERE action_type = :action', [
            'action' => 'LOGIN_FAILED',
        ]);
        self::assertGreaterThanOrEqual(2, $failedAuditCount);

        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => 'wrong',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(423);

        $connection->executeStatement('UPDATE account_lockouts al JOIN users u ON u.id = al.user_id SET al.locked_until = DATE_SUB(NOW(), INTERVAL 1 MINUTE), al.captcha_required = 0, al.failed_count = 0 WHERE u.username = :username', ['username' => $username]);
        $connection->executeStatement('DELETE FROM login_attempts WHERE username = :username', ['username' => $username]);

        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
    }
}
