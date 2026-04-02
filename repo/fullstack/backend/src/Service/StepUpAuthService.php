<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class StepUpAuthService
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function verifyStepUp(User $user, string $password, string $justification): bool
    {
        if (trim($justification) === '') {
            return false;
        }

        $valid = $this->passwordHasher->isPasswordValid($user, $password);
        if ($valid) {
            $line = json_encode([
                'user_id' => $user->getId(),
                'username' => $user->getUsername(),
                'justification' => $justification,
                'at' => (new \DateTimeImmutable())->format(DATE_ATOM),
            ], JSON_THROW_ON_ERROR);

            $logPath = dirname(__DIR__, 2) . '/var/step_up_audit.log';
            @file_put_contents($logPath, $line . PHP_EOL, FILE_APPEND);
        }

        return $valid;
    }
}
