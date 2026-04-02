<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RescheduleApiTest extends WebTestCase
{
    public function testRescheduleLimitViaApi(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'user', 'User@123');
        [$appointmentId, $slotIds] = $this->prepareConfirmedWithTargets();

        $client->request('POST', '/api/v1/appointments/' . $appointmentId . '/reschedule', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode(['new_slot_id' => $slotIds[1]], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);

        $client->request('POST', '/api/v1/appointments/' . $appointmentId . '/reschedule', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode(['new_slot_id' => $slotIds[2]], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);

        $client->request('POST', '/api/v1/appointments/' . $appointmentId . '/reschedule', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode(['new_slot_id' => $slotIds[3]], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(422);
    }

    private function prepareConfirmedWithTargets(): array
    {
        $db = static::getContainer()->get(Connection::class);
        $userId = (int) $db->fetchOne("SELECT id FROM users WHERE username='user'");
        $pid = (int) $db->fetchOne('SELECT id FROM practitioners ORDER BY id ASC LIMIT 1');
        $db->insert('locations', ['name' => 'Reschedule-' . uniqid(), 'address' => 'test', 'capacity' => 1, 'status' => 'ACTIVE', 'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')]);
        $lid = (int) $db->lastInsertId();
        $base = new \DateTimeImmutable(sprintf('+5 days +%d minutes', random_int(1, 3000)));
        $slotIds = [];
        for ($i = 0; $i < 4; $i++) {
            $start = $base->modify(sprintf('+%d hours', $i));
            $db->insert('appointment_slots', ['practitioner_id' => $pid, 'location_id' => $lid, 'start_at' => $start->format('Y-m-d H:i:s'), 'end_at' => $start->modify('+30 minutes')->format('Y-m-d H:i:s'), 'capacity' => 1, 'available_count' => 1, 'status' => 'AVAILABLE']);
            $slotIds[] = (int) $db->lastInsertId();
        }

        $db->insert('appointments', ['practitioner_id' => $pid, 'location_id' => $lid, 'slot_id' => $slotIds[0], 'booked_by' => $userId, 'state' => 'CONFIRMED', 'held_until' => null, 'reschedule_count' => 0, 'booked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'), 'cancelled_at' => null]);
        $appointmentId = (int) $db->lastInsertId();
        $db->executeStatement('UPDATE appointment_slots SET available_count = 0, status = :full WHERE id = :id', ['id' => $slotIds[0], 'full' => 'FULL']);

        return [$appointmentId, $slotIds];
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
