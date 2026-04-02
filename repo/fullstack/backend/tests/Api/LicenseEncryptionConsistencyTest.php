<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LicenseEncryptionConsistencyTest extends WebTestCase
{
    public function testRegistrationStoresEncryptedLicenseAndRevealReturnsOriginal(): void
    {
        $client = static::createClient();
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);
        $username = 'enc_' . bin2hex(random_bytes(4));
        $license = 'LIC-ENC-7788';

        $client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => 'Strong@123',
            'full_name' => 'Encryption Test',
            'firm_affiliation' => 'Eagle Point Legal',
            'license_number' => $license,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $stored = (string) $connection->fetchOne('SELECT license_number_encrypted FROM practitioners WHERE full_name = :fullName ORDER BY id DESC LIMIT 1', [
            'fullName' => 'Encryption Test',
        ]);
        self::assertNotSame(base64_encode($license), $stored);

        $practitionerId = (int) $connection->fetchOne('SELECT id FROM practitioners WHERE full_name = :fullName ORDER BY id DESC LIMIT 1', [
            'fullName' => 'Encryption Test',
        ]);
        self::assertGreaterThan(0, $practitionerId);

        $reviewerToken = $this->login($client, 'reviewer', 'Reviewer@123');
        $client->request('POST', '/api/v1/practitioners/' . $practitionerId . '/license/reveal', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $reviewerToken,
        ], content: json_encode(['reason' => 'consistency test'], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $revealed = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($license, $revealed['license_number']);
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
