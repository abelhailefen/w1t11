<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ExportTest extends WebTestCase
{
    public function testCsvExportDownload(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'content_admin', 'Content@123');
        $client->request('GET', '/api/v1/questions/export?format=csv', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/csv', (string) $client->getResponse()->headers->get('content-type'));
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
