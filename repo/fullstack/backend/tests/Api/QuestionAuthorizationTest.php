<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;

class QuestionAuthorizationTest extends ApiWebTestCase
{
    public function testQuestionEndpointsAreRestrictedToContentAdminAndSystemAdmin(): void
    {
        $client = static::createClient();

        $roleTokens = [
            'ROLE_USER' => $this->login($client, 'user', 'User@123'),
            'ROLE_ANALYST' => $this->login($client, 'analyst', 'Analyst@123'),
            'ROLE_CREDENTIAL_REVIEWER' => $this->login($client, 'reviewer', 'Reviewer@123'),
            'ROLE_CONTENT_ADMIN' => $this->login($client, 'content_admin', 'Content@123'),
            'ROLE_SYSTEM_ADMIN' => $this->login($client, 'admin', 'Admin@123'),
        ];

        foreach (['ROLE_USER', 'ROLE_ANALYST', 'ROLE_CREDENTIAL_REVIEWER'] as $role) {
            $this->assertQuestionReadAccess($client, $roleTokens[$role], 403);
        }

        foreach (['ROLE_CONTENT_ADMIN', 'ROLE_SYSTEM_ADMIN'] as $role) {
            $this->assertQuestionReadAccess($client, $roleTokens[$role], 200);
        }
    }

    private function assertQuestionReadAccess($client, string $token, int $expectedStatus): void
    {
        $client->request('GET', '/api/v1/questions', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseStatusCodeSame($expectedStatus);

        $client->request('GET', '/api/v1/questions/1', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseStatusCodeSame($expectedStatus);

        $client->request('GET', '/api/v1/questions/export?format=csv', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseStatusCodeSame($expectedStatus);

        $client->request('GET', '/api/v1/questions/1/versions', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseStatusCodeSame($expectedStatus);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
