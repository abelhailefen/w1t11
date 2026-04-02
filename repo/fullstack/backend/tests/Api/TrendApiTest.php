<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TrendApiTest extends WebTestCase
{
    public function testTrendEndpointReturnsDataPoints(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'analyst', 'Analyst@123');
        $from = (new \DateTimeImmutable('-7 days'))->format('Y-m-d');
        $to = (new \DateTimeImmutable())->format('Y-m-d');
        $client->request('GET', '/api/v1/dashboards/trend?metric=credential_submissions&from=' . $from . '&to=' . $to . '&interval=daily', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('points', $data);
        self::assertIsArray($data['points']);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
