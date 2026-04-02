<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use App\Tests\Api\ApiWebTestCase;

class SlotListTest extends ApiWebTestCase
{
    public function testReturnsOnlyAvailableSlotsInRange(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'user', 'User@123');
        [$pid, $inRange, $outRange] = $this->prepareSlots();

        $from = (new \DateTimeImmutable('+6 days'))->format('Y-m-d');
        $to = (new \DateTimeImmutable('+7 days'))->format('Y-m-d');
        $client->request('GET', "/api/v1/appointments/slots?practitioner_id={$pid}&date_from={$from}&date_to={$to}", server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        $items = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['items'];
        $ids = array_map(fn ($i) => $i['id'], $items);
        self::assertContains($inRange, $ids);
        self::assertNotContains($outRange, $ids);
    }

    private function prepareSlots(): array
    {
        $db = static::getContainer()->get(Connection::class);
        $pid = (int) $db->fetchOne('SELECT id FROM practitioners ORDER BY id ASC LIMIT 1');
        $lid = (int) $db->fetchOne("SELECT id FROM locations WHERE status='ACTIVE' ORDER BY id ASC LIMIT 1");
        $shift = random_int(1, 1800);
        $s1 = new \DateTimeImmutable(sprintf('+6 days +%d minutes', $shift));
        $db->insert('appointment_slots', ['practitioner_id' => $pid, 'location_id' => $lid, 'start_at' => $s1->format('Y-m-d H:i:s'), 'end_at' => $s1->modify('+30 minutes')->format('Y-m-d H:i:s'), 'capacity' => 1, 'available_count' => 1, 'status' => 'AVAILABLE']);
        $inRange = (int) $db->lastInsertId();
        $s2 = new \DateTimeImmutable(sprintf('+20 days +%d minutes', $shift));
        $db->insert('appointment_slots', ['practitioner_id' => $pid, 'location_id' => $lid, 'start_at' => $s2->format('Y-m-d H:i:s'), 'end_at' => $s2->modify('+30 minutes')->format('Y-m-d H:i:s'), 'capacity' => 1, 'available_count' => 1, 'status' => 'AVAILABLE']);
        return [$pid, $inRange, (int) $db->lastInsertId()];
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
