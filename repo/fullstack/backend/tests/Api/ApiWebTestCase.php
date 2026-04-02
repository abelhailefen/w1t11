<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

abstract class ApiWebTestCase extends WebTestCase
{
    protected static function createClient(array $options = [], array $server = []): KernelBrowser
    {
        $csrfToken = bin2hex(random_bytes(32));
        $server['HTTP_X_XSRF_TOKEN'] = $csrfToken;

        $client = parent::createClient($options, $server);
        $client->getCookieJar()->set(new Cookie('XSRF-TOKEN', $csrfToken));

        return $client;
    }
}
