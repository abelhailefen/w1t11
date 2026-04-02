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
        if (!$lockout) {
            $lockout = (new \App\Entity\AccountLockout())
                ->setUser($user)
                ->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($lockout);
        }
        $lockout->setFailedCount(0);
        $lockout->setCaptchaRequired(false);
        $lockout->setLockedUntil(null);

        $this->connection->executeStatement('DELETE FROM login_attempts WHERE username = :username', ['username' => 'lockout_tester']);
        $this->connection->executeStatement("UPDATE system_settings SET setting_value = '5' WHERE setting_key = 'login_lockout_attempts'");

        $this->entityManager->flush();
    }

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testLockoutLifecycle(): void
    {
        $repo = $this->entityManager->getRepository(User::class);
        /** @var User $user */
        $user = $repo->findOneBy(['username' => 'lockout_tester']);
        $lockout = $this->lockoutRepository->findOneByUser($user);
        $lockout?->setLockedUntil(new \DateTimeImmutable('+10 minutes'));
        $lockout?->setFailedCount(5);
        $lockout?->setCaptchaRequired(true);
        $this->entityManager->flush();

        $lockedStatus = $this->authService->checkLockout('lockout_tester');
        self::assertTrue($lockedStatus['locked']);
        self::assertNotNull($lockedStatus['locked_until']);

        $lockout?->setLockedUntil(new \DateTimeImmutable('-1 minute'));
        $lockout?->setFailedCount(0);
        $lockout?->setCaptchaRequired(false);
        $this->entityManager->flush();
        $this->connection->executeStatement("DELETE FROM login_attempts WHERE username = 'lockout_tester'");

        $result = $this->authService->login('lockout_tester', 'Valid@123', null, null, '127.0.0.1');
        self::assertArrayHasKey('token', $result);

        $statusReset = $this->authService->checkLockout('lockout_tester');
        self::assertFalse($statusReset['locked']);
        self::assertFalse($statusReset['captcha_required']);
    }
}
