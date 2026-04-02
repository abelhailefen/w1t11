<?php

namespace App\Tests\Api;

use App\Tests\Api\ApiWebTestCase;

class HealthEndpointTest extends ApiWebTestCase
{
    public function testHealthEndpointReturnsOk(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/health');

        self::assertResponseIsSuccessful();
        self::assertJson($client->getResponse()->getContent() ?: '{}');
    }
}
