<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class FirmCRUDTest extends WebTestCase
{
    public function testFirmCrudAsAdminAndForbiddenAsUser(): void
    {
        $client = static::createClient();
        $adminToken = $this->login($client, 'admin', 'Admin@123');

        $name = 'Firm ' . bin2hex(random_bytes(3));
        $client->request('POST', '/api/v1/admin/firms', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $adminToken,
        ], content: json_encode(['name' => $name, 'address' => '100 Main St'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $client->request('GET', '/api/v1/admin/firms', server: ['HTTP_Authorization' => 'Bearer ' . $adminToken]);
        self::assertResponseIsSuccessful();

        $client->request('PATCH', '/api/v1/admin/firms/' . $created['id'], server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $adminToken,
        ], content: json_encode(['address' => '101 Main St'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);

        $client->request('DELETE', '/api/v1/admin/firms/' . $created['id'], server: [
            'HTTP_Authorization' => 'Bearer ' . $adminToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $userToken = $this->login($client, 'user', 'User@123');
        $client->request('POST', '/api/v1/admin/firms', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $userToken,
        ], content: json_encode(['name' => 'Forbidden firm'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(403);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        return $data['token'];
    }
}
