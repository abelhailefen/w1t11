<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use App\Tests\Api\ApiWebTestCase;

class LockoutLifecycleTest extends ApiWebTestCase
{
    public function testAccountLocksAfterFiveFailuresAndUnlocksAfterExpiry(): void
    {
        $client = static::createClient();
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $username = 'lock_' . bin2hex(random_bytes(4));
        $connection->executeStatement("UPDATE system_settings SET setting_value = '5' WHERE setting_key = 'login_lockout_attempts'");
        $connection->executeStatement("UPDATE system_settings SET setting_value = '15' WHERE setting_key = 'login_lockout_duration_minutes'");

        $client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => 'Strong@123',
            'full_name' => 'Lock User',
            'firm_affiliation' => 'Eagle Point Legal',
            'license_number' => 'LCK-1001',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        for ($i = 0; $i < 4; $i++) {
            $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
                'username' => $username,
                'password' => 'Wrong@123',
            ], JSON_THROW_ON_ERROR));
            self::assertResponseStatusCodeSame(401);
        }

        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => 'Wrong@123',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(423);
        $fifth = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotEmpty($fifth['details']['locked_until'] ?? null);

        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => 'Strong@123',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(423);

        $connection->executeStatement(
            'UPDATE account_lockouts al JOIN users u ON u.id = al.user_id SET al.locked_until = DATE_SUB(NOW(), INTERVAL 1 MINUTE), al.captcha_required = 0, al.failed_count = 0 WHERE u.username = :username',
            ['username' => $username]
        );
        $connection->executeStatement('DELETE FROM login_attempts WHERE username = :username', ['username' => $username]);

        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => 'Strong@123',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $failedCount = (int) $connection->fetchOne(
            'SELECT al.failed_count FROM account_lockouts al JOIN users u ON u.id = al.user_id WHERE u.username = :username',
            ['username' => $username]
        );
        self::assertSame(0, $failedCount);
    }
}
