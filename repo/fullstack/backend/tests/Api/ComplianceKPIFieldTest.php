<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;

class ComplianceKPIFieldTest extends ApiWebTestCase
{
    public function testComplianceEndpointIncludesPromptLiteralKpis(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'analyst', 'Analyst@123');
        $from = (new \DateTimeImmutable('-30 days'))->format('Y-m-d');
        $to = (new \DateTimeImmutable())->format('Y-m-d');

        $client->request('GET', '/api/v1/dashboards/compliance?from=' . $from . '&to=' . $to, server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        foreach (['rescue_volume', 'recovery_rate', 'adoption_conversion', 'average_shelter_stay', 'supply_turnover'] as $key) {
            self::assertArrayHasKey($key, $data);
            self::assertIsNumeric($data[$key]);
        }

        self::assertArrayHasKey('donation_mix', $data);
        self::assertIsArray($data['donation_mix']);
        self::assertNotNull($data['donation_mix']);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
