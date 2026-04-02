<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use App\Tests\Api\ApiWebTestCase;

class FullFlowIntegrationTest extends ApiWebTestCase
{
    public function testAdminCrossModuleFlow(): void
    {
        $client = static::createClient();
        /** @var Connection $db */
        $db = static::getContainer()->get(Connection::class);
        $token = $this->login($client, 'admin', 'Admin@123');
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token];

        $firmName = 'Flow Firm ' . bin2hex(random_bytes(3));
        $client->request('POST', '/api/v1/admin/firms', server: $headers, content: json_encode(['name' => $firmName], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $firm = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/v1/practitioners', server: $headers, content: json_encode([
            'full_name' => 'Flow Practitioner ' . bin2hex(random_bytes(2)),
            'firm_id' => (int) $firm['id'],
            'license_number' => 'FLOW-' . random_int(1000, 9999),
            'license_jurisdiction' => 'NY',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $practitioner = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/v1/credentials', server: $headers, content: json_encode(['practitioner_id' => (int) $practitioner['id']], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $submission = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/v1/credentials/' . $submission['id'] . '/submit', server: $headers, content: '{}');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/api/v1/credentials/' . $submission['id'] . '/start-review', server: $headers, content: '{}');
        self::assertResponseIsSuccessful();
        $client->request('POST', '/api/v1/credentials/' . $submission['id'] . '/approve', server: $headers, content: '{}');
        self::assertResponseIsSuccessful();

        $slotId = (int) $db->fetchOne('SELECT id FROM appointment_slots WHERE available_count > 0 ORDER BY id ASC LIMIT 1');
        self::assertGreaterThan(0, $slotId);
        $client->request('POST', '/api/v1/appointments/hold', server: $headers, content: json_encode(['slot_id' => $slotId], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $hold = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $client->request('POST', '/api/v1/appointments/book', server: $headers, content: json_encode(['appointment_id' => (int) $hold['appointment_id']], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $categoryId = (int) $db->fetchOne('SELECT id FROM question_categories ORDER BY id ASC LIMIT 1');
        $client->request('POST', '/api/v1/questions', server: $headers, content: json_encode([
            'content_html' => '<p>Full flow question ' . bin2hex(random_bytes(2)) . '</p>',
            'category_id' => $categoryId,
            'difficulty' => 3,
            'tags' => [],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $question = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/v1/questions/' . $question['id'] . '/publish', server: $headers, content: json_encode(['ack_duplicates' => true], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $from = (new \DateTimeImmutable('-30 days'))->format('Y-m-d');
        $to = (new \DateTimeImmutable())->format('Y-m-d');
        $client->request('GET', '/api/v1/dashboards/compliance?from=' . $from . '&to=' . $to, server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
