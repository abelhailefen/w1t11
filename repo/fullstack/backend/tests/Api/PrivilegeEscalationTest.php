<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PrivilegeEscalationTest extends WebTestCase
{
    public function testRegisterIgnoresSuppliedPrivilegedRoleAndAdminRolePatchStillWorks(): void
    {
        $client = static::createClient();
        $username = 'escalation_' . bin2hex(random_bytes(4));

        $client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => 'Strong@123',
            'full_name' => 'Escalation User',
            'firm_affiliation' => 'Eagle Point Legal',
            'license_number' => 'LIC-ES-1001',
            'role' => 'ROLE_SYSTEM_ADMIN',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $token = $this->login($client, $username, 'Strong@123');
        $client->request('GET', '/api/v1/auth/me', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        $me = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ROLE_USER', $me['role']);

        $adminToken = $this->login($client, 'admin', 'Admin@123');
        $client->request('GET', '/api/v1/admin/users', server: ['HTTP_Authorization' => 'Bearer ' . $adminToken]);
        self::assertResponseIsSuccessful();
        $users = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['items'];
        $target = array_values(array_filter($users, fn (array $u) => $u['username'] === $username));
        self::assertNotEmpty($target);

        $client->request('PATCH', '/api/v1/admin/users/' . $target[0]['id'] . '/role', server: [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_Authorization' => 'Bearer ' . $adminToken,
        ], content: json_encode(['role' => 'ROLE_CREDENTIAL_REVIEWER'], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $tokenAfterPatch = $this->login($client, $username, 'Strong@123');
        $client->request('GET', '/api/v1/auth/me', server: ['HTTP_Authorization' => 'Bearer ' . $tokenAfterPatch]);
        self::assertResponseIsSuccessful();
        $meAfterPatch = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('ROLE_CREDENTIAL_REVIEWER', $meAfterPatch['role']);
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
