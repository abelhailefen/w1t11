<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;
use Symfony\Component\BrowserKit\Cookie;

class CsrfProtectionTest extends ApiWebTestCase
{
    public function testProtectedStateChangingEndpointsRequireMatchingXsrfToken(): void
    {
        $client = static::createClient();

        // Excluded endpoints should work without CSRF token
        $client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => 'csrf_reg_' . bin2hex(random_bytes(4)),
            'password' => 'Strong@123',
            'full_name' => 'CSRF Register',
            'firm_affiliation' => 'Eagle Point Legal',
            'license_number' => 'CSRF-REG-1',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $login = $this->login($client, 'user', 'User@123');

        // GET remains read-only and is not CSRF-protected
        $client->request('GET', '/api/v1/auth/me', server: ['HTTP_Authorization' => 'Bearer ' . $login]);
        self::assertResponseIsSuccessful();

        // Missing header + existing cookie -> 403
        $client->getCookieJar()->set(new Cookie('XSRF-TOKEN', 'csrf-good-token'));
        $client->request('POST', '/api/v1/auth/logout', server: [
            'HTTP_Authorization' => 'Bearer ' . $login,
            'HTTP_X_XSRF_TOKEN' => '',
        ]);
        self::assertResponseStatusCodeSame(403);
        $forbidden = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('CSRF token mismatch', $forbidden['message'] ?? null);

        // Wrong header -> 403
        $client->request('POST', '/api/v1/auth/logout', server: [
            'HTTP_Authorization' => 'Bearer ' . $login,
            'HTTP_X_XSRF_TOKEN' => 'csrf-wrong-token',
        ]);
        self::assertResponseStatusCodeSame(403);

        // Correct header + cookie -> success
        $client->request('POST', '/api/v1/auth/logout', server: [
            'HTTP_Authorization' => 'Bearer ' . $login,
            'HTTP_X_XSRF_TOKEN' => 'csrf-good-token',
        ]);
        self::assertResponseIsSuccessful();
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
