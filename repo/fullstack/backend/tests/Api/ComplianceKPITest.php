<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;

class ComplianceKPITest extends ApiWebTestCase
{
    public function testComplianceEndpointReturnsKpis(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'analyst', 'Analyst@123');
        $from = (new \DateTimeImmutable('-30 days'))->format('Y-m-d');
        $to = (new \DateTimeImmutable())->format('Y-m-d');
        $client->request('GET', '/api/v1/dashboards/compliance?from=' . $from . '&to=' . $to, server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        foreach (['credential_review_volume','approval_rate','rejection_rate','avg_review_turnaround_hours','appointment_utilization_rate','question_bank_growth','question_publish_rate'] as $key) {
            self::assertArrayHasKey($key, $data);
            self::assertIsNumeric($data[$key]);
        }
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
