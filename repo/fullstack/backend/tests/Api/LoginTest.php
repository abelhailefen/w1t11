<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LoginTest extends WebTestCase
{
    public function testLoginAndLockoutFlow(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => 'admin',
            'password' => 'Admin@123',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('token', $data);

        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => 'admin',
            'password' => 'wrong',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(401);

        for ($i = 0; $i < 5; $i++) {
            $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
                'username' => 'analyst',
                'password' => 'wrong',
            ], JSON_THROW_ON_ERROR));
        }

        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => 'analyst',
            'password' => 'Analyst@123',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(423);
    }
}
