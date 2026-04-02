<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\AccountLockoutRepository;
use App\Service\ApiException;
use App\Service\AuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LockoutLogicTest extends KernelTestCase
{
    private AuthService $authService;
    private EntityManagerInterface $entityManager;
    private AccountLockoutRepository $lockoutRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->authService = $container->get(AuthService::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->lockoutRepository = $container->get(AccountLockoutRepository::class);

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

    public function testLockoutLifecycle(): void
    {
        for ($i = 0; $i < 4; $i++) {
            try {
                $this->authService->login('lockout_tester', 'wrong-password', null, null, '127.0.0.1');
            } catch (ApiException) {
            }
        }

        $statusBefore = $this->authService->checkLockout('lockout_tester');
        self::assertFalse($statusBefore['locked']);

        try {
            $this->authService->login('lockout_tester', 'wrong-password', null, null, '127.0.0.1');
            self::fail('Expected lockout exception');
        } catch (ApiException $exception) {
            self::assertSame(401, $exception->getHttpCode());
        }

        $statusAfter = $this->authService->checkLockout('lockout_tester');
        self::assertTrue($statusAfter['locked']);

        try {
            $this->authService->login('lockout_tester', 'Valid@123', null, null, '127.0.0.1');
            self::fail('Expected lockout response');
        } catch (ApiException $exception) {
            self::assertSame(423, $exception->getHttpCode());
        }

        $user = $this->entityManager->getRepository(User::class)->findOneBy(['username' => 'lockout_tester']);
        $lockout = $this->lockoutRepository->findOneByUser($user);
        $lockout->setLockedUntil(new \DateTimeImmutable('-1 minute'));
        $lockout->setCaptchaRequired(false);
        $lockout->setFailedCount(0);
        $this->entityManager->flush();

        $result = $this->authService->login('lockout_tester', 'Valid@123', null, null, '127.0.0.1');
        self::assertArrayHasKey('token', $result);
    }
}
