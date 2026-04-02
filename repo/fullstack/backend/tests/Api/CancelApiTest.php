<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use App\Tests\Api\ApiWebTestCase;

class CancelApiTest extends ApiWebTestCase
{
    public function testCancelRules(): void
    {
        $client = static::createClient();
        $userToken = $this->login($client, 'user', 'User@123');
        $adminToken = $this->login($client, 'admin', 'Admin@123');
        [$apptFar, $apptNear] = $this->prepareAppointments();

        $client->request('POST', '/api/v1/appointments/' . $apptFar . '/cancel', server: ['HTTP_Authorization' => 'Bearer ' . $userToken]);
        self::assertResponseStatusCodeSame(200);

        $client->request('POST', '/api/v1/appointments/' . $apptNear . '/cancel', server: ['HTTP_Authorization' => 'Bearer ' . $userToken]);
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/api/v1/appointments/' . $apptNear . '/cancel', server: ['HTTP_Authorization' => 'Bearer ' . $adminToken]);
        self::assertResponseStatusCodeSame(200);
    }

    private function prepareAppointments(): array
    {
        $db = static::getContainer()->get(Connection::class);
        $userId = (int) $db->fetchOne("SELECT id FROM users WHERE username='user'");
        $pid = (int) $db->fetchOne('SELECT id FROM practitioners ORDER BY id ASC LIMIT 1');
        $lid = (int) $db->fetchOne("SELECT id FROM locations WHERE status='ACTIVE' ORDER BY id ASC LIMIT 1");

        $make = function (string $startAt) use ($db, $pid, $lid, $userId): int {
            $s = new \DateTimeImmutable($startAt);
            $db->insert('appointment_slots', ['practitioner_id' => $pid, 'location_id' => $lid, 'start_at' => $s->format('Y-m-d H:i:s'), 'end_at' => $s->modify('+30 minutes')->format('Y-m-d H:i:s'), 'capacity' => 1, 'available_count' => 0, 'status' => 'FULL']);
            $slotId = (int) $db->lastInsertId();
            $db->insert('appointments', ['practitioner_id' => $pid, 'location_id' => $lid, 'slot_id' => $slotId, 'booked_by' => $userId, 'state' => 'CONFIRMED', 'held_until' => null, 'reschedule_count' => 0, 'booked_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'), 'cancelled_at' => null]);
            return (int) $db->lastInsertId();
        };

        return [
            $make(sprintf('+3 days +%d minutes', random_int(1, 3000))),
            $make(sprintf('+5 hours +%d minutes', random_int(1, 120))),
        ];
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
