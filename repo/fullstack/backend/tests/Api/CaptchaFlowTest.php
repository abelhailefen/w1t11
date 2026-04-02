<?php

namespace App\Tests\Api;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CaptchaFlowTest extends WebTestCase
{
    public function testCaptchaGetAndVerify(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $client->request('GET', '/api/v1/auth/captcha');
        self::assertResponseIsSuccessful();
        $payload = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('image', $payload);
        self::assertArrayHasKey('token', $payload);

        $row = $connection->fetchAssociative('SELECT challenge_payload FROM captcha_challenges WHERE token = :token', ['token' => $payload['token']]);
        $challengePayload = json_decode((string) $row['challenge_payload'], true, flags: JSON_THROW_ON_ERROR);

        $client->request('POST', '/api/v1/auth/captcha/verify', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'token' => $payload['token'],
            'answer' => $challengePayload['answer'],
        ], JSON_THROW_ON_ERROR));
        self::assertResponseIsSuccessful();

        $second = json_decode((string) $client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($second['verified']);

        $challenge = $container->get(Connection::class)->fetchAssociative('SELECT token FROM captcha_challenges ORDER BY id DESC LIMIT 1');
        $client->request('POST', '/api/v1/auth/captcha/verify', server: ['CONTENT_TYPE' => 'application/json'], content: json_encode([
            'token' => $challenge['token'],
            'answer' => 'wrong',
        ], JSON_THROW_ON_ERROR));
        self::assertResponseStatusCodeSame(400);
    }
}
