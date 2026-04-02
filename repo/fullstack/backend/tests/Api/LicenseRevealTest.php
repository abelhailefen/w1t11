<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class LicenseRevealTest extends WebTestCase
{
    public function testRevealAuthorizationAndAuditLogging(): void
    {
        $client = static::createClient();
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);

        $reviewerToken = $this->login($client, 'reviewer', 'Reviewer@123');

        $client->request('POST', '/api/v1/practitioners', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $reviewerToken,
        ], content: json_encode([
            'full_name' => 'Reveal Tester',
            'firm_id' => 1,
            'license_number' => 'CA-11112222',
            'license_jurisdiction' => 'CA',
        ], JSON_THROW_ON_ERROR));
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/v1/practitioners/' . $created['id'] . '/license/reveal', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $reviewerToken,
        ], content: json_encode(['reason' => 'Quality review'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
        $reveal = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('CA-11112222', $reveal['license_number']);

        $userToken = $this->login($client, 'user', 'User@123');
        $client->request('POST', '/api/v1/practitioners/' . $created['id'] . '/license/reveal', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $userToken,
        ], content: json_encode(['reason' => 'Unauthorized'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(403);

        $count = (int) $connection->fetchOne('SELECT COUNT(*) FROM sensitive_access_logs WHERE entity_type = :entity AND entity_id = :id', [
            'entity' => 'practitioner',
            'id' => $created['id'],
        ]);
        self::assertGreaterThanOrEqual(1, $count);
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
