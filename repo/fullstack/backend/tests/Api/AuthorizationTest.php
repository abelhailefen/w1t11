<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthorizationTest extends WebTestCase
{
    public function testNonAnalystGets403(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'user', 'User@123');
        $client->request('GET', '/api/v1/dashboards/compliance', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseStatusCodeSame(403);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
