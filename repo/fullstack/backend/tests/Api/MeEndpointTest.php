<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;

class MeEndpointTest extends ApiWebTestCase
{
    public function testMeEndpointAuthorization(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/v1/auth/me');
        self::assertResponseStatusCodeSame(401);

        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => 'admin',
            'password' => 'Admin@123',
        ], JSON_THROW_ON_ERROR));

        $loginData = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $token = $loginData['token'];

        $client->request('GET', '/api/v1/auth/me', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
    }
}
