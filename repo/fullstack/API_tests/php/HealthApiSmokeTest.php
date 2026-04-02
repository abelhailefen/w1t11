<?php

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HealthApiSmokeTest extends WebTestCase
{
    public function testHealthEndpointIsReachable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/v1/health');

        self::assertResponseStatusCodeSame(200);
    }
}
