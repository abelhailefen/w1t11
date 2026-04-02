<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;

class AdminUserTest extends ApiWebTestCase
{
    public function testAdminEndpointsAccessAndActions(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAndGetToken($client, 'admin', 'Admin@123');
        $userToken = $this->loginAndGetToken($client, 'user', 'User@123');

        $client->request('GET', '/api/v1/admin/users', server: ['HTTP_Authorization' => 'Bearer ' . $adminToken]);
        self::assertResponseIsSuccessful();
        $items = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['items'];
        self::assertNotEmpty($items);

        $client->request('GET', '/api/v1/admin/users', server: ['HTTP_Authorization' => 'Bearer ' . $userToken]);
        self::assertResponseStatusCodeSame(403);

        $targetUserId = $items[0]['id'];

        $client->request(
            'PATCH',
            '/api/v1/admin/users/' . $targetUserId . '/role',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $adminToken],
            content: json_encode(['role' => 'ROLE_USER'], JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();

        $client->request(
            'POST',
            '/api/v1/admin/users/' . $targetUserId . '/reset-password',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $adminToken],
            content: json_encode([], JSON_THROW_ON_ERROR)
        );
        self::assertResponseIsSuccessful();
    }

    private function loginAndGetToken($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));

        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        return $data['token'];
    }
}
