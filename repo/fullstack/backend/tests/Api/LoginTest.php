<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LoginTest extends WebTestCase
{
    public function testLoginAndSmartCaptchaFlow(): void
    {
        $client = static::createClient();
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $username = 'captcha_' . bin2hex(random_bytes(4));
        $password = 'Strong@123';

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

        for ($i = 0; $i < 2; $i++) {
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
        self::assertResponseStatusCodeSame(403);
        $thirdFail = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('NEED_CAPTCHA', $thirdFail['details']['error_code'] ?? null);

        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', '/api/v1/auth/captcha');
        $captcha = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $row = $connection->fetchAssociative('SELECT challenge_payload FROM captcha_challenges WHERE token = :token', ['token' => $captcha['token']]);
        $challengePayload = json_decode((string) $row['challenge_payload'], true, flags: JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
            'captcha_token' => $captcha['token'],
            'captcha_answer' => (string) $challengePayload['answer'],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
    }
}
