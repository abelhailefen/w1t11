<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use App\Tests\Api\ApiWebTestCase;

class AuditIntegrationTest extends ApiWebTestCase
{
    public function testLoginAndPractitionerCreateProduceAuditLogs(): void
    {
        $client = static::createClient();
        /** @var Connection $db */
        $db = static::getContainer()->get(Connection::class);

        $db->executeStatement("DELETE FROM audit_logs WHERE action_type IN ('LOGIN','CREATE')");
        $token = $this->login($client, 'admin', 'Admin@123');

        $client->request('POST', '/api/v1/practitioners', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $token,
        ], content: json_encode([
            'full_name' => 'Audit Create Practitioner',
            'firm_id' => 1,
            'license_number' => 'AUD-1001',
            'license_jurisdiction' => 'NY',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $loginCount = (int) $db->fetchOne("SELECT COUNT(*) FROM audit_logs WHERE action_type = 'LOGIN'");
        $createCount = (int) $db->fetchOne("SELECT COUNT(*) FROM audit_logs WHERE action_type = 'CREATE' AND entity_type LIKE '%Practitioner%'");
        self::assertGreaterThanOrEqual(1, $loginCount);
        self::assertGreaterThanOrEqual(1, $createCount);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
