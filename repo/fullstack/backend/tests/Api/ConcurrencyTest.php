<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use App\Tests\Api\ApiWebTestCase;

class ConcurrencyTest extends ApiWebTestCase
{
    public function testTwoParallelHoldsOnlyOneSucceeds(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'user', 'User@123');
        $csrfToken = (string) $client->getCookieJar()->get('XSRF-TOKEN')?->getValue();
        $slotId = $this->createSingleCapacitySlot();
        $url = 'http://localhost/api/v1/appointments/hold';
        $payload = json_encode(['slot_id' => $slotId], JSON_THROW_ON_ERROR);

        $ch1 = curl_init($url);
        $ch2 = curl_init($url);
        foreach ([$ch1, $ch2] as $ch) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'X-XSRF-TOKEN: ' . $csrfToken,
                'Cookie: XSRF-TOKEN=' . $csrfToken,
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $mh = curl_multi_init();
        curl_multi_add_handle($mh, $ch1);
        curl_multi_add_handle($mh, $ch2);
        do {
            curl_multi_exec($mh, $running);
        } while ($running > 0);

        $code1 = (int) curl_getinfo($ch1, CURLINFO_HTTP_CODE);
        $code2 = (int) curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch1);
        curl_multi_remove_handle($mh, $ch2);
        curl_multi_close($mh);

        $codes = [$code1, $code2];
        sort($codes);
        self::assertSame([200, 409], $codes);
    }

    private function createSingleCapacitySlot(): int
    {
        $db = static::getContainer()->get(Connection::class);
        $pid = (int) $db->fetchOne('SELECT id FROM practitioners ORDER BY id ASC LIMIT 1');
        $lid = (int) $db->fetchOne("SELECT id FROM locations WHERE status='ACTIVE' ORDER BY id ASC LIMIT 1");
        $start = new \DateTimeImmutable(sprintf('+4 days +%d minutes', random_int(1, 3500)));
        $db->insert('appointment_slots', ['practitioner_id' => $pid, 'location_id' => $lid, 'start_at' => $start->format('Y-m-d H:i:s'), 'end_at' => $start->modify('+30 minutes')->format('Y-m-d H:i:s'), 'capacity' => 1, 'available_count' => 1, 'status' => 'AVAILABLE']);
        return (int) $db->lastInsertId();
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
