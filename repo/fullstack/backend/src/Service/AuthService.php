<?php

namespace App\Service;

use App\Entity\AccountLockout;
use App\Entity\LoginAttempt;
use App\Entity\User;
use App\Enum\UserRole;
use App\Enum\UserStatus;
use App\Repository\AccountLockoutRepository;
use App\Repository\LoginAttemptRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoginAttemptRepository $loginAttemptRepository,
        private readonly AccountLockoutRepository $accountLockoutRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly CaptchaService $captchaService,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function register(string $username, string $password, string $role): User
    {
        if ($this->userRepository->findOneByUsername($username)) {
            throw new ApiException('Username already exists', 409);
        }

        $user = (new User())
            ->setUsername($username)
            ->setRole(UserRole::from($role))
            ->setStatus(UserStatus::ACTIVE)
            ->setCreatedAt(new \DateTimeImmutable())
            ->setUpdatedAt(new \DateTimeImmutable());

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function login(string $username, string $password, ?string $captchaToken, ?string $captchaAnswer, ?string $ipAddress): array
    {
        $user = $this->userRepository->findOneByUsername($username);
        $lockout = $user ? $this->accountLockoutRepository->findOneByUser($user) : null;

        if ($user && $user->getStatus() === UserStatus::DISABLED) {
            $this->recordAttempt($username, null, $user, false, $ipAddress);
            throw new ApiException('Account is disabled', 403);
        }

        if ($user && $lockout && $lockout->getLockedUntil() && $lockout->getLockedUntil() > new \DateTimeImmutable()) {
            if ($lockout->isCaptchaRequired()) {
                if (!$captchaToken || !$captchaAnswer || !$this->captchaService->verify($captchaToken, $captchaAnswer)) {
                    $this->recordAttempt($username, $lockout, $user, false, $ipAddress);
                    throw new ApiException('Account locked; CAPTCHA required', 423, ['captcha_required' => true]);
                }
            }

            $this->recordAttempt($username, $lockout, $user, false, $ipAddress);
            throw new ApiException('Account is temporarily locked', 423, ['captcha_required' => $lockout->isCaptchaRequired()]);
        }

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->recordAttempt($username, $lockout, $user, false, $ipAddress);
            throw new ApiException('Invalid credentials', 401, ['captcha_required' => $this->checkLockout($username)['captcha_required']]);
        }

        if ($lockout) {
            $lockout->setFailedCount(0);
            $lockout->setLockedUntil(null);
            $lockout->setCaptchaRequired(false);
        }

        $user->setStatus(UserStatus::ACTIVE);
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->recordAttempt($username, $lockout, $user, true, $ipAddress);

        return [
            'token' => $this->jwtTokenManager->create($user),
            'user' => $user,
        ];
    }

    public function checkLockout(string $username): array
    {
        $user = $this->userRepository->findOneByUsername($username);
        if (!$user) {
            return ['locked' => false, 'captcha_required' => false, 'locked_until' => null];
        }

        $lockout = $this->accountLockoutRepository->findOneByUser($user);
        if (!$lockout || !$lockout->getLockedUntil() || $lockout->getLockedUntil() <= new \DateTimeImmutable()) {
            return ['locked' => false, 'captcha_required' => false, 'locked_until' => null];
        }

        return [
            'locked' => true,
            'captcha_required' => $lockout->isCaptchaRequired(),
            'locked_until' => $lockout->getLockedUntil()->format(DATE_ATOM),
        ];
    }

    private function recordAttempt(string $username, ?AccountLockout $lockout, ?User $user, bool $success, ?string $ipAddress): void
    {
        if (!$success && $user) {
            if (!$lockout) {
                $lockout = (new AccountLockout())
                    ->setUser($user)
                    ->setFailedCount(0)
                    ->setCaptchaRequired(false)
                    ->setCreatedAt(new \DateTimeImmutable());
                $this->entityManager->persist($lockout);
            }

            $nextCount = $lockout->getFailedCount() + 1;
            $lockout->setFailedCount($nextCount);

            if ($nextCount >= 5) {
                $lockout->setLockedUntil(new \DateTimeImmutable('+15 minutes'));
                $lockout->setCaptchaRequired(true);
                $user->setStatus(UserStatus::LOCKED);
            }
        }

        $attempt = (new LoginAttempt())
            ->setUsername($username)
            ->setUser($user)
            ->setIpAddress($ipAddress)
            ->setSuccess($success)
            ->setAttemptedAt(new \DateTimeImmutable());

        $this->entityManager->persist($attempt);
        $this->entityManager->flush();
    }
}
