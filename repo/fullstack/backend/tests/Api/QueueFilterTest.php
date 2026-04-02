<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class QueueFilterTest extends WebTestCase
{
    public function testQueueCanBeFilteredByState(): void
    {
        $client = static::createClient();
        $userToken = $this->login($client, 'user', 'User@123');
        $reviewerToken = $this->login($client, 'reviewer', 'Reviewer@123');

        $client->request('POST', '/api/v1/credentials', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $userToken,
        ], content: json_encode(['practitioner_id' => 1], JSON_THROW_ON_ERROR));
        $id = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];

        $client->request('POST', '/api/v1/credentials/' . $id . '/submit', server: ['HTTP_Authorization' => 'Bearer ' . $userToken]);

        $client->request('GET', '/api/v1/credentials/queue?state=SUBMITTED', server: ['HTTP_Authorization' => 'Bearer ' . $reviewerToken]);
        self::assertResponseIsSuccessful();

        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotEmpty($data['items']);
        foreach ($data['items'] as $item) {
            self::assertSame('SUBMITTED', $item['current_state']);
        }
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
