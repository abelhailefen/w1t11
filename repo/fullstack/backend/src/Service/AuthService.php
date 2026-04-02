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
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    private const CAPTCHA_THRESHOLD = 3;
    private const CAPTCHA_WINDOW_MINUTES = 15;

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly LoginAttemptRepository $loginAttemptRepository,
        private readonly AccountLockoutRepository $accountLockoutRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtTokenManager,
        private readonly CaptchaService $captchaService,
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection
    ) {
    }

    public function register(
        string $username,
        string $password,
        string $role,
        string $fullName,
        string $firmAffiliation,
        string $licenseNumber
    ): User {
        if ($this->userRepository->findOneByUsername($username)) {
            throw new ApiException('Username already exists', 409);
        }

        $this->assertPasswordComplexity($password);
        $now = new \DateTimeImmutable();

        $user = (new User())
            ->setUsername($username)
            ->setRole(UserRole::from($role))
            ->setStatus(UserStatus::ACTIVE)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $firmId = $this->upsertFirm($firmAffiliation, $now);
        $this->connection->insert('practitioners', [
            'firm_id' => $firmId,
            'full_name' => $fullName,
            'license_number_encrypted' => base64_encode($licenseNumber),
            'license_jurisdiction' => 'N/A',
            'contact_email' => null,
            'contact_phone' => null,
            'status' => 'ACTIVE',
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return $user;
    }

    public function login(string $username, string $password, ?string $captchaToken, ?string $captchaAnswer, ?string $ipAddress): array
    {
        $now = new \DateTimeImmutable();
        $user = $this->userRepository->findOneByUsername($username);
        $lockout = $user ? $this->accountLockoutRepository->findOneByUser($user) : null;

        if ($this->isCaptchaRequired($username, $now) && (!$captchaToken || !$captchaAnswer || !$this->captchaService->verify($captchaToken, $captchaAnswer))) {
            $this->recordAttempt($username, $lockout, $user, false, $ipAddress, $now);
            throw new ApiException('CAPTCHA required', 403, [
                'captcha_required' => true,
                'error_code' => 'NEED_CAPTCHA',
            ]);
        }

        if ($user && $user->getStatus() === UserStatus::DISABLED) {
            $this->recordAttempt($username, null, $user, false, $ipAddress, $now);
            throw new ApiException('Account is disabled', 403);
        }

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->recordAttempt($username, $lockout, $user, false, $ipAddress, $now);

            if ($this->isCaptchaRequired($username, $now)) {
                throw new ApiException('CAPTCHA required', 403, [
                    'captcha_required' => true,
                    'error_code' => 'NEED_CAPTCHA',
                ]);
            }

            throw new ApiException('Invalid credentials', 401, ['captcha_required' => false]);
        }

        if ($lockout) {
            $lockout->setFailedCount(0);
            $lockout->setLockedUntil(null);
            $lockout->setCaptchaRequired(false);
        }

        $user->setStatus(UserStatus::ACTIVE);
        $user->setUpdatedAt($now);
        $this->recordAttempt($username, $lockout, $user, true, $ipAddress, $now);

        return [
            'token' => $this->jwtTokenManager->create($user),
            'user' => $user,
        ];
    }

    public function checkLockout(string $username): array
    {
        return [
            'locked' => false,
            'captcha_required' => $this->isCaptchaRequired($username, new \DateTimeImmutable()),
            'locked_until' => null,
        ];
    }

    private function recordAttempt(
        string $username,
        ?AccountLockout $lockout,
        ?User $user,
        bool $success,
        ?string $ipAddress,
        \DateTimeImmutable $attemptedAt
    ): void {
        if (!$success) {
            $nextCount = $this->countConsecutiveFailures($username, $attemptedAt) + 1;
            if ($user) {
                if (!$lockout) {
                    $lockout = (new AccountLockout())
                        ->setUser($user)
                        ->setFailedCount(0)
                        ->setCaptchaRequired(false)
                        ->setCreatedAt($attemptedAt);
                    $this->entityManager->persist($lockout);
                }

                $lockout->setFailedCount($nextCount);
                $lockout->setCaptchaRequired($nextCount >= self::CAPTCHA_THRESHOLD);
                $lockout->setLockedUntil(null);
                $user->setStatus(UserStatus::ACTIVE);
                $user->setUpdatedAt($attemptedAt);
            }

            $this->recordFailedLoginAudit($username, $user, $ipAddress, $attemptedAt, $nextCount >= self::CAPTCHA_THRESHOLD);
        } elseif ($user && $lockout) {
            $lockout->setFailedCount(0);
            $lockout->setCaptchaRequired(false);
            $lockout->setLockedUntil(null);
        }

        $attempt = (new LoginAttempt())
            ->setUsername($username)
            ->setUser($user)
            ->setIpAddress($ipAddress)
            ->setSuccess($success)
            ->setAttemptedAt($attemptedAt);

        $this->entityManager->persist($attempt);
        $this->entityManager->flush();
    }

    private function isCaptchaRequired(string $username, \DateTimeImmutable $now): bool
    {
        return $this->countConsecutiveFailures($username, $now) >= self::CAPTCHA_THRESHOLD;
    }

    private function countConsecutiveFailures(string $username, \DateTimeImmutable $now): int
    {
        $since = $now->sub(new \DateInterval('PT' . self::CAPTCHA_WINDOW_MINUTES . 'M'));
        $attempts = $this->loginAttemptRepository->findRecentByUsername($username, $since);

        $consecutiveFailures = 0;
        foreach ($attempts as $attempt) {
            if ($attempt->isSuccess()) {
                break;
            }
            $consecutiveFailures++;
        }

        return $consecutiveFailures;
    }

    private function assertPasswordComplexity(string $password): void
    {
        $isComplex = preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/', $password) === 1;
        if (!$isComplex) {
            throw new ApiException(
                'Password must include uppercase, lowercase, number, and special character',
                400,
                ['password' => 'Password complexity requirements not met']
            );
        }
    }

    private function upsertFirm(string $firmName, \DateTimeImmutable $now): int
    {
        $existing = $this->connection->fetchOne('SELECT id FROM firms WHERE name = :name', ['name' => $firmName]);
        if ($existing !== false) {
            return (int) $existing;
        }

        $this->connection->insert('firms', [
            'name' => $firmName,
            'address' => null,
            'status' => 'ACTIVE',
            'created_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    private function recordFailedLoginAudit(
        string $username,
        ?User $user,
        ?string $ipAddress,
        \DateTimeImmutable $occurredAt,
        bool $captchaRequired
    ): void {
        $this->connection->insert('audit_logs', [
            'occurred_at' => $occurredAt->format('Y-m-d H:i:s'),
            'user_id' => $user?->getId(),
            'action_type' => 'LOGIN_FAILED',
            'entity_type' => 'User',
            'entity_id' => $user?->getId(),
            'old_value_json' => null,
            'new_value_json' => json_encode([
                'username' => $username,
                'captcha_required' => $captchaRequired,
            ], JSON_THROW_ON_ERROR),
            'ip_address' => $ipAddress,
            'retention_expires_at' => $occurredAt->add(new \DateInterval('P365D'))->format('Y-m-d H:i:s'),
        ]);
    }
}
