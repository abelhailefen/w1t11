<?php

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExternalRegisterTest extends WebTestCase
{
    public function testRegisterEndpointExists(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => 'api_ext_' . bin2hex(random_bytes(4)),
            'password' => 'Strong@123',
            'role' => 'ROLE_USER',
        ], JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(201);
    }
}
