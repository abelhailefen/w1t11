<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use App\Tests\Api\ApiWebTestCase;

class BookingFlowTest extends ApiWebTestCase
{
    public function testHoldThenConfirm(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'user', 'User@123');
        $slotId = $this->createSlot(sprintf('+4 days +%d minutes', random_int(1, 3000)));

        $client->request('POST', '/api/v1/appointments/hold', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode(['slot_id' => $slotId], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
        $appointmentId = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['appointment_id'];

        $client->request('POST', '/api/v1/appointments/book', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode(['appointment_id' => $appointmentId], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
    }

    private function createSlot(string $when): int
    {
        $db = static::getContainer()->get(Connection::class);
        $pid = (int) $db->fetchOne('SELECT id FROM practitioners ORDER BY id ASC LIMIT 1');
        $db->insert('locations', ['name' => 'BookFlow-' . uniqid(), 'address' => 'test', 'capacity' => 1, 'status' => 'ACTIVE', 'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
        $lid = (int) $db->lastInsertId();
        $start = new \DateTimeImmutable($when);
        $db->insert('appointment_slots', ['practitioner_id' => $pid, 'location_id' => $lid, 'start_at' => $start->format('Y-m-d H:i:s'), 'end_at' => $start->modify('+30 minutes')->format('Y-m-d H:i:s'), 'capacity' => 1, 'available_count' => 1, 'status' => 'AVAILABLE']);
        return (int) $db->lastInsertId();
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
