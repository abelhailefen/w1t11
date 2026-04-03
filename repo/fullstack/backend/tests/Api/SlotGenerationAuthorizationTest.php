<?php

namespace App\Tests\Api;

class SlotGenerationAuthorizationTest extends ApiWebTestCase
{
    public function testSlotGenerationRequiresSystemAdmin(): void
    {
        $body = json_encode([
            'date_from' => (new \DateTimeImmutable('+1 day'))->format('Y-m-d'),
            'date_to' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d'),
        ], JSON_THROW_ON_ERROR);

        $userClient = static::createClient();
        $userToken = $this->login($userClient, 'user', 'User@123');
        $userClient->request('POST', '/api/v1/appointments/slots/generate', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $userToken,
        ], content: $body);
        self::assertResponseStatusCodeSame(403);

        $analystClient = static::createClient();
        $analystToken = $this->login($analystClient, 'analyst', 'Analyst@123');
        $analystClient->request('POST', '/api/v1/appointments/slots/generate', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $analystToken,
        ], content: $body);
        self::assertResponseStatusCodeSame(403);

        $contentAdminClient = static::createClient();
        $contentAdminToken = $this->login($contentAdminClient, 'content_admin', 'Content@123');
        $contentAdminClient->request('POST', '/api/v1/appointments/slots/generate', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $contentAdminToken,
        ], content: $body);
        self::assertResponseStatusCodeSame(403);

        $adminClient = static::createClient();
        $adminToken = $this->login($adminClient, 'admin', 'Admin@123');
        $adminClient->request('POST', '/api/v1/appointments/slots/generate', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $adminToken,
        ], content: $body);
        self::assertContains($adminClient->getResponse()->getStatusCode(), [200, 201]);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => $password,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
