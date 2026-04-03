<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class CredentialFileAuthorizationTest extends ApiWebTestCase
{
    public function testCredentialFilesRequireOwnerReviewerOrAdmin(): void
    {
        $ownerClient = static::createClient();
        $ownerUsername = 'owner_user_' . bin2hex(random_bytes(3));
        $ownerClient->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $ownerUsername,
            'password' => 'Owner@123',
            'full_name' => 'Credential Owner User',
            'firm_affiliation' => 'Owner Firm',
            'license_number' => 'OW-' . bin2hex(random_bytes(3)),
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $ownerToken = $this->login($ownerClient, $ownerUsername, 'Owner@123');

        $ownerClient->request('POST', '/api/v1/practitioners', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $ownerToken,
        ], content: json_encode([
            'full_name' => 'Owner Credential Practitioner',
            'firm_id' => 1,
            'license_number' => 'OWNER-1234',
            'license_jurisdiction' => 'NY',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $practitioner = json_decode((string) $ownerClient->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $db = static::getContainer()->get(Connection::class);
        $ownerId = (int) $db->fetchOne('SELECT id FROM users WHERE username = :u', ['u' => $ownerUsername]);
        $createdBy = (int) $db->fetchOne('SELECT created_by FROM practitioners WHERE id = :id', ['id' => (int) $practitioner['id']]);
        self::assertSame($ownerId, $createdBy);

        $pdfPath = tempnam(sys_get_temp_dir(), 'owner_pdf_');
        file_put_contents($pdfPath, "%PDF-1.4\n%EOF");
        $pdf = new UploadedFile($pdfPath, 'owner-doc.pdf', 'application/pdf', null, true);
        $ownerClient->request('POST', '/api/v1/practitioners/' . $practitioner['id'] . '/credentials/upload', server: [
            'HTTP_Authorization' => 'Bearer ' . $ownerToken,
        ], files: ['file' => $pdf]);
        self::assertResponseStatusCodeSame(201);
        $uploaded = json_decode((string) $ownerClient->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $submissionOwner = (int) $db->fetchOne('SELECT created_by_id FROM credential_submissions WHERE practitioner_id = :pid ORDER BY id DESC LIMIT 1', ['pid' => (int) $practitioner['id']]);
        self::assertSame($ownerId, $submissionOwner);

        $otherClient = static::createClient();
        $username = 'cred_user_' . bin2hex(random_bytes(3));
        $otherClient->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => 'User2@123',
            'full_name' => 'Credential User B',
            'firm_affiliation' => 'Firm B',
            'license_number' => 'UB-' . bin2hex(random_bytes(3)),
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $otherToken = $this->login($otherClient, $username, 'User2@123');
        $otherClient->request('GET', '/api/v1/practitioners/' . $practitioner['id'] . '/credentials', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $otherToken,
        ]);
        self::assertResponseStatusCodeSame(403);

        $otherClient->request('GET', '/api/v1/practitioners/' . $practitioner['id'] . '/credentials/' . $uploaded['id'] . '/download', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $otherToken,
        ]);
        self::assertResponseStatusCodeSame(403);

        $ownerVerifyClient = static::createClient();
        $ownerVerifyToken = $this->login($ownerVerifyClient, $ownerUsername, 'Owner@123');
        $ownerVerifyClient->request('GET', '/api/v1/practitioners/' . $practitioner['id'] . '/credentials', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerVerifyToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $ownerVerifyClient->request('GET', '/api/v1/practitioners/' . $practitioner['id'] . '/credentials/' . $uploaded['id'] . '/download', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $ownerVerifyToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $reviewerClient = static::createClient();
        $reviewerToken = $this->login($reviewerClient, 'reviewer', 'Reviewer@123');
        $reviewerClient->request('GET', '/api/v1/practitioners/' . $practitioner['id'] . '/credentials', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $reviewerToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $reviewerClient->request('GET', '/api/v1/practitioners/' . $practitioner['id'] . '/credentials/' . $uploaded['id'] . '/download', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $reviewerToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $adminClient = static::createClient();
        $adminToken = $this->login($adminClient, 'admin', 'Admin@123');
        $adminClient->request('GET', '/api/v1/practitioners/' . $practitioner['id'] . '/credentials', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $adminClient->request('GET', '/api/v1/practitioners/' . $practitioner['id'] . '/credentials/' . $uploaded['id'] . '/download', server: [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $adminToken,
        ]);
        self::assertResponseStatusCodeSame(200);
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
