<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;

class QuestionRollbackApiTest extends ApiWebTestCase
{
    public function testRollbackAdminAllowedNonAdminForbidden(): void
    {
        $client = static::createClient();
        $admin = $this->login($client, 'admin', 'Admin@123');
        $content = $this->login($client, 'content_admin', 'Content@123');

        $client->request('POST', '/api/v1/questions', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $content], content: json_encode([
            'content_html' => '<p>rollback q v1</p>',
            'category_id' => 1,
            'difficulty' => 3,
            'tags' => [],
        ], JSON_THROW_ON_ERROR));
        $id = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['id'];
        $client->request('PATCH', '/api/v1/questions/' . $id, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $content], content: json_encode(['content_html' => '<p>rollback q v2</p>'], JSON_THROW_ON_ERROR));

        $client->request('POST', '/api/v1/questions/' . $id . '/rollback', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $content], content: json_encode(['target_version_no' => 1, 'password' => 'Content@123', 'justification' => 'need revert'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(403);

        $client->request('POST', '/api/v1/questions/' . $id . '/rollback', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_Authorization' => 'Bearer ' . $admin], content: json_encode(['target_version_no' => 1, 'password' => 'Admin@123', 'justification' => 'need revert'], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
