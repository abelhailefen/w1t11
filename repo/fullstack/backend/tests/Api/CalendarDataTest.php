<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;

class CalendarDataTest extends ApiWebTestCase
{
    public function testCalendarWeekStructure(): void
    {
        $client = static::createClient();
        $token = $this->login($client, 'user', 'User@123');
        $week = (new \DateTimeImmutable('monday this week'))->format('Y-m-d');
        $client->request('GET', '/api/v1/appointments/calendar?week=' . $week . '&practitioner_id=1', server: ['HTTP_Authorization' => 'Bearer ' . $token]);
        self::assertResponseIsSuccessful();
        $data = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('days', $data);
        self::assertCount(7, $data['days']);
    }

    private function login($client, string $username, string $password): string
    {
        $client->request('POST', '/api/v1/auth/login', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['username' => $username, 'password' => $password], JSON_THROW_ON_ERROR));
        return json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR)['token'];
    }
}
