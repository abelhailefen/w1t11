<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RollbackAPITest extends WebTestCase
{
    public function testRollbackScenarios(): void
    {
        $client = static::createClient();
        $userToken = $this->login($client, 'user', 'User@123');
        $reviewerToken = $this->login($client, 'reviewer', 'Reviewer@123');
        $adminToken = $this->login($client, 'admin', 'Admin@123');

        $client->request('POST', '/api/v1/credentials', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $userToken,
        ], content: json_encode(['practitioner_id' => 1], JSON_THROW_ON_ERROR));
        $id = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];

        $client->request('POST', '/api/v1/credentials/' . $id . '/submit', server: ['HTTP_Authorization' => 'Bearer ' . $userToken]);
        $client->request('POST', '/api/v1/credentials/' . $id . '/start-review', server: ['HTTP_Authorization' => 'Bearer ' . $reviewerToken]);
        $client->request('POST', '/api/v1/credentials/' . $id . '/approve', server: ['HTTP_Authorization' => 'Bearer ' . $reviewerToken]);

        $client->request('POST', '/api/v1/credentials/' . $id . '/rollback', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $userToken,
        ], content: json_encode([
            'target_version_no' => 2,
            'password' => 'User@123',
            'justification' => 'not allowed',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/api/v1/credentials/' . $id . '/rollback', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $adminToken,
        ], content: json_encode([
            'target_version_no' => 2,
            'password' => 'bad-password',
            'justification' => 'rollback after review',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(401);

        $client->request('POST', '/api/v1/credentials/' . $id . '/rollback', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $adminToken,
        ], content: json_encode([
            'target_version_no' => 2,
            'password' => 'Admin@123',
            'justification' => 'rollback after review',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
