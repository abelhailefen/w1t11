<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;

class ExportPdfTest extends ApiWebTestCase
{
    public function testPdfExportContentType(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'analyst', 'Analyst@123');
        $from = (new \DateTimeImmutable('-30 days'))->format('Y-m-d');
        $to = (new \DateTimeImmutable())->format('Y-m-d');
        $client->request('GET', '/api/v1/reports/export.pdf?dashboard=compliance&from=' . $from . '&to=' . $to, server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('application/pdf', (string) $client->getResponse()->headers->get('content-type'));
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
