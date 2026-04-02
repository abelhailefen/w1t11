<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;

class NavigationSmokeTest extends ApiWebTestCase
{
    public function testMenuLinkedApiEndpointsRespond(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'admin', 'Admin@123');
        $headers = ['HTTP_Authorization' => 'Bearer ' . $token];

        $paths = [
            '/api/v1/auth/me',
            '/api/v1/practitioners',
            '/api/v1/credentials/queue',
            '/api/v1/appointments',
            '/api/v1/questions',
            '/api/v1/analytics/queries',
            '/api/v1/dashboards/compliance',
            '/api/v1/admin/users',
            '/api/v1/admin/firms',
            '/api/v1/admin/locations',
            '/api/v1/admin/org-units',
            '/api/v1/admin/question-categories',
            '/api/v1/admin/question-tags',
            '/api/v1/admin/settings',
            '/api/v1/audit/logs',
            '/api/v1/alerts',
        ];

        foreach ($paths as $path) {
            $client->request('GET', $path, server: $headers);
            self::assertNotSame(404, $client->getResponse()->getStatusCode(), sprintf('%s returned 404', $path));
        }
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
