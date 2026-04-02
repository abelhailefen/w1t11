<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;

class CredentialWorkflowTest extends ApiWebTestCase
{
    public function testLifecycleCreateSubmitReviewApprove(): void
    {
        $client = static::createClient();
        $userToken = $this->login($client, 'user', 'User@123');
        $reviewerToken = $this->login($client, 'reviewer', 'Reviewer@123');

        $client->request('POST', '/api/v1/credentials', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $userToken,
        ], content: json_encode(['practitioner_id' => 1], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/v1/credentials/' . $created['id'] . '/submit', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $userToken,
        ], content: '{}');
        self::assertResponseStatusCodeSame(200);

        $client->request('POST', '/api/v1/credentials/' . $created['id'] . '/start-review', server: [
            'HTTP_Authorization' => 'Bearer ' . $reviewerToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $client->request('POST', '/api/v1/credentials/' . $created['id'] . '/approve', server: [
            'HTTP_Authorization' => 'Bearer ' . $reviewerToken,
        ]);
        self::assertResponseStatusCodeSame(200);
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
