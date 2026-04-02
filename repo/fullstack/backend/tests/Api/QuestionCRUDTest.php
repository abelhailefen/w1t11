<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class QuestionCRUDTest extends WebTestCase
{
    public function testCreateListUpdateVersions(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'content_admin', 'Content@123');

        $client->request('POST', '/api/v1/questions', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode([
            'content_html' => '<p>CRUD test question</p>',
            'category_id' => 1,
            'difficulty' => 3,
            'tags' => [],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $id = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];

        $client->request('GET', '/api/v1/questions?status=DRAFT', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();

        $client->request('PATCH', '/api/v1/questions/' . $id, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode([
            'content_html' => '<p>CRUD test question updated</p>',
            'difficulty' => 4,
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $client->request('GET', '/api/v1/questions/' . $id . '/versions', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(2, count(json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['items']));
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
