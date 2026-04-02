<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PractitionerCRUDTest extends WebTestCase
{
    public function testPractitionerCrudFlow(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin', 'Admin@123');

        $client->request('POST', '/api/v1/practitioners', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $token,
        ], content: json_encode([
            'full_name' => 'CRUD Tester',
            'firm_id' => 1,
            'license_number' => 'NY-998877',
            'license_jurisdiction' => 'NY',
            'contact_email' => 'crud@example.local',
            'contact_phone' => '+1-555-0200',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $client->request('GET', '/api/v1/practitioners?page=1&limit=20', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        $list = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('items', $list);

        $client->request('GET', '/api/v1/practitioners/' . $created['id'], server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        $detail = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertStringStartsWith('***-', $detail['license_number']);

        $client->request('PATCH', '/api/v1/practitioners/' . $created['id'], server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $token,
        ], content: json_encode(['contact_phone' => '+1-555-0201'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        return $data['token'];
    }
}
