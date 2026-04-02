<?php

namespace App\Tests\Unit;

use App\Service\CaptchaService;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CaptchaServiceTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testGenerateAndVerifyCaptcha(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var CaptchaService $captchaService */
        $captchaService = $container->get(CaptchaService::class);
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $challenge = $captchaService->generateChallenge();
        self::assertArrayHasKey('image', $challenge);
        self::assertArrayHasKey('token', $challenge);
        self::assertNotEmpty($challenge['image']);

        $row = $connection->fetchAssociative('SELECT challenge_payload FROM captcha_challenges WHERE token = :token', ['token' => $challenge['token']]);
        $payload = json_decode((string) $row['challenge_payload'], true, flags: JSON_THROW_ON_ERROR);

        self::assertTrue($captchaService->verify($challenge['token'], (string) $payload['answer']));

        $challenge2 = $captchaService->generateChallenge();
        self::assertFalse($captchaService->verify($challenge2['token'], 'wrong-answer'));
    }
}
