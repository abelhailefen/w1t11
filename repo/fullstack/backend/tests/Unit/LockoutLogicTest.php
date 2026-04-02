<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\AccountLockoutRepository;
use App\Service\ApiException;
use App\Service\AuthService;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LockoutLogicTest extends KernelTestCase
{
    private AuthService $authService;
    private EntityManagerInterface $entityManager;
    private AccountLockoutRepository $lockoutRepository;
    private Connection $connection;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->authService = $container->get(AuthService::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->lockoutRepository = $container->get(AccountLockoutRepository::class);
        $this->connection = $container->get(Connection::class);

        $repo = $this->entityManager->getRepository(User::class);
        $user = $repo->findOneBy(['username' => 'lockout_tester']);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        if (!$user) {
            $user = (new User())
                ->setUsername('lockout_tester')
                ->setRole(UserRole::ROLE_USER)
                ->setStatus(UserStatus::ACTIVE)
                ->setCreatedAt(new \DateTimeImmutable())
                ->setUpdatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($user);
        }

        $user->setPassword($hasher->hashPassword($user, 'Valid@123'));
        $user->setStatus(UserStatus::ACTIVE);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $lockout = $this->lockoutRepository->findOneByUser($user);
        if ($lockout) {
            $lockout->setFailedCount(0);
            $lockout->setCaptchaRequired(false);
            $lockout->setLockedUntil(null);
        }

        $this->entityManager->flush();
    }

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testCaptchaTriggerLifecycle(): void
    {
        for ($i = 0; $i < 2; $i++) {
            try {
                $this->authService->login('lockout_tester', 'wrong-password', null, null, '127.0.0.1');
            } catch (ApiException) {
            }
        }

        $statusBefore = $this->authService->checkLockout('lockout_tester');
        self::assertFalse($statusBefore['captcha_required']);

        try {
            $this->authService->login('lockout_tester', 'wrong-password', null, null, '127.0.0.1');
            self::fail('Expected captcha requirement exception');
        } catch (ApiException $exception) {
            self::assertSame(403, $exception->getHttpCode());
            self::assertSame('NEED_CAPTCHA', $exception->getDetails()['error_code'] ?? null);
        }

        $statusAfter = $this->authService->checkLockout('lockout_tester');
        self::assertTrue($statusAfter['captcha_required']);

        try {
            $this->authService->login('lockout_tester', 'Valid@123', null, null, '127.0.0.1');
            self::fail('Expected captcha requirement response');
        } catch (ApiException $exception) {
            self::assertSame(403, $exception->getHttpCode());
            self::assertSame('NEED_CAPTCHA', $exception->getDetails()['error_code'] ?? null);
        }

        $challenge = static::getContainer()->get(\App\Service\CaptchaService::class)->generateChallenge();
        $row = $this->connection->fetchAssociative('SELECT challenge_payload FROM captcha_challenges WHERE token = :token', ['token' => $challenge['token']]);
        $payload = json_decode((string) $row['challenge_payload'], true, flags: JSON_THROW_ON_ERROR);

        $result = $this->authService->login('lockout_tester', 'Valid@123', $challenge['token'], (string) $payload['answer'], '127.0.0.1');
        self::assertArrayHasKey('token', $result);

        $statusReset = $this->authService->checkLockout('lockout_tester');
        self::assertFalse($statusReset['captcha_required']);
    }
}
