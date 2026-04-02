<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ExportCsvTest extends WebTestCase
{
    public function testCsvExportContentType(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'analyst', 'Analyst@123');

        $client->request('POST', '/api/v1/analytics/queries/save', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode([
            'name' => 'Export CSV test',
            'query_definition' => [
                'entity_type' => 'appointments',
                'filters' => [],
                'aggregation' => 'count',
                'group_by' => '',
            ],
        ], JSON_THROW_ON_ERROR));
        $id = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];

        $client->request('GET', '/api/v1/reports/export.csv?query_id=' . $id, server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/csv', (string) $client->getResponse()->headers->get('content-type'));
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
