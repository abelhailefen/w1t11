<?php

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExternalAdminUserTest extends WebTestCase
{
    public function testAdminCanListUsers(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => 'admin',
            'password' => 'Admin@123',
        ], JSON_THROW_ON_ERROR));
        $token = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];

        $client->request('GET', '/api/v1/admin/users', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseStatusCodeSame(200);
    }
}
