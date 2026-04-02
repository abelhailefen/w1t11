<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PublishWithDuplicateTest extends WebTestCase
{
    public function testPublishDuplicateAndUnique(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'content_admin', 'Content@123');

        $id1 = $this->createQuestion($client, $token, '<p>duplicate baseline phrase alpha beta gamma</p>');
        $client->request('POST', '/api/v1/questions/' . $id1 . '/publish', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: '{}');

        $id2 = $this->createQuestion($client, $token, '<p>duplicate baseline phrase alpha beta gamma</p>');
        $client->request('POST', '/api/v1/questions/' . $id2 . '/publish', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: '{}');
        self::assertResponseIsSuccessful();
        $dup = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($dup['published']);
        self::assertNotEmpty($dup['warnings']);

        $id3 = $this->createQuestion($client, $token, '<p>UNIQUE_' . bin2hex(random_bytes(16)) . '_' . bin2hex(random_bytes(16)) . '</p>');
        $client->request('POST', '/api/v1/questions/' . $id3 . '/publish', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: '{}');
        $unique = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($unique['published']);
        self::assertCount(0, $unique['warnings']);
    }

    private function createQuestion($client, string $token, string $html): int
    {
        $client->request('POST', '/api/v1/questions', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode([
            'content_html' => $html,
            'category_id' => 1,
            'difficulty' => 3,
            'tags' => [],
        ], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
