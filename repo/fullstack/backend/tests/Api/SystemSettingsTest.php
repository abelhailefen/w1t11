<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SystemSettingsTest extends WebTestCase
{
    public function testGetPutGetSettings(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin', 'Admin@123');

        $client->request('GET', '/api/v1/admin/settings', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('appointment_slot_minutes', $data['items']);
        self::assertArrayHasKey('login_lockout_attempts', $data['items']);

        $client->request('PUT', '/api/v1/admin/settings', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode([
            'items' => [
                'appointment_slot_minutes' => '35',
                'login_lockout_attempts' => '6',
            ],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/v1/admin/settings', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        $updated = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('35', (string) $updated['items']['appointment_slot_minutes']);
        self::assertSame('6', (string) $updated['items']['login_lockout_attempts']);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
