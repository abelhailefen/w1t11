<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;

class SecurityBoundaryAuthorizationTest extends ApiWebTestCase
{
    public function testCrossUserCancelIsForbidden(): void
    {
        $db = static::getContainer()->get(Connection::class);

        $userAClient = static::createClient();
        $userAToken = $this->login($userAClient, 'user', 'User@123');
        $slotId = $this->createSlot($db, '+70 days');

        $userAClient->request('POST', '/api/v1/appointments/hold', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $userAToken,
        ], content: json_encode(['slot_id' => $slotId], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
        $appointmentId = (int) (json_decode((string) $userAClient->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['appointment_id'] ?? 0);

        $userAClient->request('POST', '/api/v1/appointments/book', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $userAToken,
        ], content: json_encode(['appointment_id' => $appointmentId], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);

        $userBClient = static::createClient();
        $username = 'cancel_user_' . bin2hex(random_bytes(3));
        $userBClient->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => 'User2@123',
            'full_name' => 'Cancel User B',
            'firm_affiliation' => 'Firm B',
            'license_number' => 'CU-' . bin2hex(random_bytes(3)),
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $userBToken = $this->login($userBClient, $username, 'User2@123');

        $userBClient->request('POST', '/api/v1/appointments/' . $appointmentId . '/cancel', server: [
            'HTTP_Authorization' => 'Bearer ' . $userBToken,
        ]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testNonAdminCannotPatchAnotherUsersPractitioner(): void
    {
        $adminClient = static::createClient();
        $adminToken = $this->login($adminClient, 'admin', 'Admin@123');

        $adminClient->request('POST', '/api/v1/practitioners', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $adminToken,
        ], content: json_encode([
            'full_name' => 'Patch Target Practitioner',
            'firm_id' => 1,
            'license_number' => 'PATCH-1001',
            'license_jurisdiction' => 'NY',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $practitionerId = (int) (json_decode((string) $adminClient->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'] ?? 0);

        $userClient = static::createClient();
        $userToken = $this->login($userClient, 'user', 'User@123');
        $userClient->request('PATCH', '/api/v1/practitioners/' . $practitionerId, server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $userToken,
        ], content: json_encode(['contact_phone' => '+1-555-9090'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(403);
    }

    private function createSlot(Connection $db, string $startOffset): int
    {
        $pid = (int) $db->fetchOne('SELECT id FROM practitioners ORDER BY id ASC LIMIT 1');
        $lid = (int) $db->fetchOne("SELECT id FROM locations WHERE status='ACTIVE' ORDER BY id ASC LIMIT 1");
        $start = (new \DateTimeImmutable($startOffset))->modify(sprintf('+%d minutes', random_int(1, 3500)));
        $db->insert('appointment_slots', [
            'practitioner_id' => $pid,
            'location_id' => $lid,
            'start_at' => $start->format('Y-m-d H:i:s'),
            'end_at' => $start->modify('+30 minutes')->format('Y-m-d H:i:s'),
            'capacity' => 1,
            'available_count' => 1,
            'status' => 'AVAILABLE',
        ]);

        return (int) $db->lastInsertId();
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
