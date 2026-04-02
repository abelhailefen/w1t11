<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TagAndCategoryTest extends WebTestCase
{
    public function testCrudForTagsAndCategories(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin', 'Admin@123');

        $client->request('POST', '/api/v1/admin/question-tags', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode(['name' => 'tag-' . uniqid()], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $tagId = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
        $client->request('PATCH', '/api/v1/admin/question-tags/' . $tagId, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode(['name' => 'tag-updated-' . uniqid()], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $client->request('DELETE', '/api/v1/admin/question-tags/' . $tagId, server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/v1/admin/question-categories', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode(['name' => 'cat-' . uniqid()], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);
        $catId = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
        $client->request('PATCH', '/api/v1/admin/question-categories/' . $catId, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $token], content: json_encode(['description' => 'updated'], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();
        $client->request('DELETE', '/api/v1/admin/question-categories/' . $catId, server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
