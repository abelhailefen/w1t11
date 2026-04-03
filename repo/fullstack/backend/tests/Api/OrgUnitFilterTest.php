<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;

class OrgUnitFilterTest extends ApiWebTestCase
{
    public function testComplianceKpisRespectOrgUnitFilter(): void
    {
        $db = static::getContainer()->get(Connection::class);
        $stamp = '2001-01-15 10:00:00';

        $db->insert('org_units', ['name' => 'KPI Org 1 ' . bin2hex(random_bytes(2)), 'parent_id' => null, 'created_at' => $stamp]);
        $org1 = (int) $db->lastInsertId();
        $db->insert('org_units', ['name' => 'KPI Org 2 ' . bin2hex(random_bytes(2)), 'parent_id' => null, 'created_at' => $stamp]);
        $org2 = (int) $db->lastInsertId();

        $db->insert('firms', ['name' => 'KPI Firm 1 ' . bin2hex(random_bytes(2)), 'address' => null, 'status' => 'ACTIVE', 'created_at' => $stamp, 'org_unit_id' => $org1]);
        $firm1 = (int) $db->lastInsertId();
        $db->insert('firms', ['name' => 'KPI Firm 2 ' . bin2hex(random_bytes(2)), 'address' => null, 'status' => 'ACTIVE', 'created_at' => $stamp, 'org_unit_id' => $org2]);
        $firm2 = (int) $db->lastInsertId();

        $reviewerId = (int) $db->fetchOne("SELECT id FROM users WHERE username = 'reviewer'");

        $seedSubmission = function (int $firmId, string $state) use ($db, $stamp, $reviewerId): void {
            $db->insert('practitioners', [
                'firm_id' => $firmId,
                'full_name' => 'Org KPI Practitioner ' . bin2hex(random_bytes(3)),
                'license_number_encrypted' => 'ENC',
                'license_jurisdiction' => 'NY',
                'contact_email' => null,
                'contact_phone' => null,
                'status' => 'ACTIVE',
                'created_at' => $stamp,
                'updated_at' => $stamp,
            ]);
            $pid = (int) $db->lastInsertId();
            $db->insert('credential_submissions', [
                'practitioner_id' => $pid,
                'current_state' => $state,
                'created_by_id' => $reviewerId,
                'created_at' => $stamp,
                'updated_at' => $stamp,
            ]);
        };

        $seedSubmission($firm1, 'APPROVED');
        $seedSubmission($firm1, 'REJECTED');
        $seedSubmission($firm2, 'APPROVED');
        $seedSubmission($firm2, 'APPROVED');
        $seedSubmission($firm2, 'REJECTED');

        $client = static::createClient();
        $token = $this->login($client, 'analyst', 'Analyst@123');

        $client->request('GET', '/api/v1/dashboards/compliance?from=2001-01-15&to=2001-01-15&org_unit=' . $org1, server: [
            'HTTP_Authorization' => 'Bearer ' . $token,
        ]);
        self::assertResponseStatusCodeSame(200);
        $org1Data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $client->request('GET', '/api/v1/dashboards/compliance?from=2001-01-15&to=2001-01-15&org_unit=' . $org2, server: [
            'HTTP_Authorization' => 'Bearer ' . $token,
        ]);
        self::assertResponseStatusCodeSame(200);
        $org2Data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $client->request('GET', '/api/v1/dashboards/compliance?from=2001-01-15&to=2001-01-15', server: [
            'HTTP_Authorization' => 'Bearer ' . $token,
        ]);
        self::assertResponseStatusCodeSame(200);
        $allData = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(2, (int) $org1Data['credential_review_volume']);
        self::assertSame(3, (int) $org2Data['credential_review_volume']);
        self::assertGreaterThanOrEqual(5, (int) $allData['credential_review_volume']);
        self::assertNotSame($org1Data['credential_review_volume'], $org2Data['credential_review_volume']);
        self::assertNotSame($org1Data['credential_review_volume'], $allData['credential_review_volume']);
        self::assertNotSame($org2Data['credential_review_volume'], $allData['credential_review_volume']);
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
