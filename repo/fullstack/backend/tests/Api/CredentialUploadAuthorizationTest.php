<?php

namespace App\Tests\Api;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class CredentialUploadAuthorizationTest extends ApiWebTestCase
{
    public function testUploadRequiresOwnerReviewerOrAdmin(): void
    {
        $ownerClient = static::createClient();
        $ownerUsername = 'upload_owner_' . bin2hex(random_bytes(3));
        $this->register($ownerClient, $ownerUsername, 'Owner@123');
        $ownerToken = $this->login($ownerClient, $ownerUsername, 'Owner@123');

        $ownerClient->request('POST', '/api/v1/practitioners', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $ownerToken,
        ], content: json_encode([
            'full_name' => 'Upload Owner Practitioner',
            'firm_id' => 1,
            'license_number' => 'UP-' . strtoupper(bin2hex(random_bytes(3))),
            'license_jurisdiction' => 'NY',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $practitionerId = json_decode((string) $ownerClient->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];

        $otherClient = static::createClient();
        $otherUsername = 'upload_other_' . bin2hex(random_bytes(3));
        $this->register($otherClient, $otherUsername, 'Other@123');
        $otherToken = $this->login($otherClient, $otherUsername, 'Other@123');
        $otherClient->request('POST', '/api/v1/practitioners/' . $practitionerId . '/credentials/upload', server: [
            'HTTP_Authorization' => 'Bearer ' . $otherToken,
        ], files: ['file' => $this->makePdf('blocked-upload.pdf')]);
        self::assertResponseStatusCodeSame(403);

        $ownerClient->request('POST', '/api/v1/practitioners/' . $practitionerId . '/credentials/upload', server: [
            'HTTP_Authorization' => 'Bearer ' . $ownerToken,
        ], files: ['file' => $this->makePdf('owner-upload.pdf')]);
        self::assertContains($ownerClient->getResponse()->getStatusCode(), [200, 201]);

        $reviewerClient = static::createClient();
        $reviewerToken = $this->login($reviewerClient, 'reviewer', 'Reviewer@123');
        $reviewerClient->request('POST', '/api/v1/practitioners/' . $practitionerId . '/credentials/upload', server: [
            'HTTP_Authorization' => 'Bearer ' . $reviewerToken,
        ], files: ['file' => $this->makePdf('reviewer-upload.pdf')]);
        self::assertContains($reviewerClient->getResponse()->getStatusCode(), [200, 201]);

        $adminClient = static::createClient();
        $adminToken = $this->login($adminClient, 'admin', 'Admin@123');
        $adminClient->request('POST', '/api/v1/practitioners/' . $practitionerId . '/credentials/upload', server: [
            'HTTP_Authorization' => 'Bearer ' . $adminToken,
        ], files: ['file' => $this->makePdf('admin-upload.pdf')]);
        self::assertContains($adminClient->getResponse()->getStatusCode(), [200, 201]);
    }

    private function makePdf(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'cred_pdf_');
        file_put_contents($path, "%PDF-1.4\n%EOF");
        return new UploadedFile($path, $name, 'application/pdf', null, true);
    }

    private function register($client, string $username, string $password): void
    {
        $client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
            'full_name' => 'Credential Upload User',
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
