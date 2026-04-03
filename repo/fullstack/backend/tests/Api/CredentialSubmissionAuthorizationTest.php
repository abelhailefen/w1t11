<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;

class CredentialSubmissionAuthorizationTest extends ApiWebTestCase
{
    public function testSubmissionCreationRequiresPractitionerOwnershipForRegularUsers(): void
    {
        $ownerClient = static::createClient();
        $ownerUsername = 'submission_owner_' . bin2hex(random_bytes(3));
        $this->register($ownerClient, $ownerUsername, 'Owner@123');
        $ownerToken = $this->login($ownerClient, $ownerUsername, 'Owner@123');

        $ownerClient->request('POST', '/api/v1/practitioners', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $ownerToken,
        ], content: json_encode([
            'full_name' => 'Submission Owner Practitioner',
            'firm_id' => 1,
            'license_number' => 'SB-' . strtoupper(bin2hex(random_bytes(3))),
            'license_jurisdiction' => 'NY',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $practitionerId = json_decode((string) $ownerClient->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
        $db = static::getContainer()->get(Connection::class);
        $ownerId = (int) $db->fetchOne('SELECT id FROM users WHERE username = :username', ['username' => $ownerUsername]);
        $createdBy = (int) $db->fetchOne('SELECT created_by FROM practitioners WHERE id = :id', ['id' => $practitionerId]);
        self::assertSame($ownerId, $createdBy);

        $otherClient = static::createClient();
        $otherUsername = 'submission_other_' . bin2hex(random_bytes(3));
        $this->register($otherClient, $otherUsername, 'Other@123');
        $otherToken = $this->login($otherClient, $otherUsername, 'Other@123');

        $otherClient->request('POST', '/api/v1/credentials', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $otherToken,
        ], content: json_encode(['practitioner_id' => $practitionerId], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(403);

        $ownerVerifyClient = static::createClient();
        $ownerVerifyToken = $this->login($ownerVerifyClient, $ownerUsername, 'Owner@123');
        $ownerVerifyClient->request('POST', '/api/v1/credentials', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $ownerVerifyToken,
        ], content: json_encode(['practitioner_id' => $practitionerId], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
    }

    private function register($client, string $username, string $password): void
    {
        $client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
            'full_name' => 'Credential Submission User',
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
