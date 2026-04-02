<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class QueryExecutionTest extends WebTestCase
{
    public function testQueryExecutionAndInvalidEntityType(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'analyst', 'Analyst@123');
        $client->request('POST', '/api/v1/analytics/query', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode([
            'entity_type' => 'practitioners',
            'filters' => ['status' => 'ACTIVE'],
            'aggregation' => 'count',
            'group_by' => 'status',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/v1/analytics/query', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode([
            'entity_type' => 'bad_entity',
            'aggregation' => 'count',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
