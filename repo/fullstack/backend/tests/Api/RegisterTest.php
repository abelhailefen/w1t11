<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RegisterTest extends WebTestCase
{
    public function testRegisterFlow(): void
    {
        $client = static::createClient();
        $username = 'reg_' . bin2hex(random_bytes(4));

        $client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => 'Strong@123',
            'full_name' => 'Test Practitioner',
            'firm_affiliation' => 'Eagle Point Legal',
            'license_number' => 'LIC-12345',
            'role' => 'ROLE_USER',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => $username,
            'password' => 'Strong@123',
            'full_name' => 'Test Practitioner',
            'firm_affiliation' => 'Eagle Point Legal',
            'license_number' => 'LIC-12345',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(409);

        $client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => '',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);

        $client->request('POST', '/api/v1/auth/register', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'username' => 'weak_' . bin2hex(random_bytes(3)),
            'password' => 'password',
            'full_name' => 'Weak Password User',
            'firm_affiliation' => 'Eagle Point Legal',
            'license_number' => 'LIC-98765',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
    }
}
