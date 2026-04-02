<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AlertApiTest extends WebTestCase
{
    public function testAlertListAcknowledgeAndFilter(): void
    {
        $client = static::createClient();
        /** @var Connection $db */
        $db = static::getContainer()->get(Connection::class);
        $db->executeStatement('DELETE FROM alerts');
        $db->insert('alerts', [
            'alert_type' => 'FAILED_LOGIN_SPIKE',
            'severity' => 'MEDIUM',
            'message' => 'test',
            'context_json' => '{"username":"u"}',
            'triggered_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'acknowledged_by' => null,
            'acknowledged_at' => null,
        ]);
        $alertId = (int) $db->lastInsertId();

        $token = $this->login($client, 'admin', 'Admin@123');
        $client->request('GET', '/api/v1/alerts?acknowledged=false', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/v1/alerts/' . $alertId . '/acknowledge', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/v1/alerts?acknowledged=false', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $payload['items']);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
