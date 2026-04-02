<?php

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExternalLoginTest extends WebTestCase
{
    public function testValidLoginReturnsJwt(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => 'admin',
            'password' => 'Admin@123',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(200);
    }
}
