<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;

class CredentialObjectAuthTest extends ApiWebTestCase
{
    public function testCredentialVersionsRequireOwnerReviewerOrAdmin(): void
    {
        $client = static::createClient();
        $userA = 'owner_' . bin2hex(random_bytes(3));
        $userB = 'other_' . bin2hex(random_bytes(3));

        $this->register($client, $userA);
        $this->register($client, $userB);

        $tokenA = $this->login($client, $userA, 'Strong@123');
        $tokenB = $this->login($client, $userB, 'Strong@123');
        $reviewerToken = $this->login($client, 'reviewer', 'Reviewer@123');
        $adminToken = $this->login($client, 'admin', 'Admin@123');

        $client->request('POST', '/api/v1/practitioners', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $tokenA,
        ], content: json_encode([
            'full_name' => 'Owner Practitioner',
            'firm_id' => 1,
            'license_number' => 'OWN-1234',
            'license_jurisdiction' => 'NY',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $practitionerId = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];

        $client->request('POST', '/api/v1/credentials', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $tokenA,
        ], content: json_encode(['practitioner_id' => $practitionerId], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $submissionId = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];

        $client->request('GET', '/api/v1/credentials/' . $submissionId . '/versions', server: ['HTTP_Authorization' => 'Bearer ' . $tokenB]);
        self::assertResponseStatusCodeSame(403);

        $client->request('GET', '/api/v1/credentials/' . $submissionId . '/versions', server: ['HTTP_Authorization' => 'Bearer ' . $reviewerToken]);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/v1/credentials/' . $submissionId . '/versions', server: ['HTTP_Authorization' => 'Bearer ' . $adminToken]);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/v1/credentials/' . $submissionId . '/versions', server: ['HTTP_Authorization' => 'Bearer ' . $tokenA]);
        self::assertResponseIsSuccessful();
    }

    private function register($client, string $username): void
    {
        $client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => 'Strong@123',
            'full_name' => 'Test User',
            'firm_affiliation' => 'Eagle Point Legal',
            'license_number' => 'LIC-' . strtoupper(bin2hex(random_bytes(3))),
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
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
