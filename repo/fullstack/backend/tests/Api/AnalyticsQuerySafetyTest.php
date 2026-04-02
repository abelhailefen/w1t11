<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;
use Symfony\Component\BrowserKit\Cookie;

class AnalyticsQuerySafetyTest extends ApiWebTestCase
{
    public function testAnalyticsQueryFieldValidation(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'analyst', 'Analyst@123');

        $client->request('GET', '/api/v1/auth/me', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();

        $csrfToken = bin2hex(random_bytes(16));
        $client->getCookieJar()->set(new Cookie('XSRF-TOKEN', $csrfToken, null, '/', 'localhost'));
        $client->getCookieJar()->set(new Cookie('XSRF-TOKEN', $csrfToken, null, '/', '127.0.0.1'));

        $client->request('POST', '/api/v1/analytics/query', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $token,
            'HTTP_X_XSRF_TOKEN' => $csrfToken,
        ], content: json_encode([
            'entity_type' => 'practitioners',
            'aggregation' => 'count',
            'group_by' => 'status',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(200);

        $client->request('POST', '/api/v1/analytics/query', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $token,
            'HTTP_X_XSRF_TOKEN' => $csrfToken,
        ], content: json_encode([
            'entity_type' => 'practitioners',
            'aggregation' => 'count',
            'group_by' => '1; DROP TABLE users',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('Invalid field', (string) $client->getResponse()->getContent());

        $client->request('POST', '/api/v1/analytics/query', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $token,
            'HTTP_X_XSRF_TOKEN' => $csrfToken,
        ], content: json_encode([
            'entity_type' => 'questions',
            'aggregation' => 'avg',
            'aggregation_field' => 'nonexistent_column',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
        self::assertStringContainsString('Invalid field', (string) $client->getResponse()->getContent());
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
