<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Service\StepUpAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/admin')]
class AdminUserController extends ApiController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly StepUpAuthService $stepUpAuthService,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/users', methods: ['GET'])]
    public function listUsers(): JsonResponse
    {
        $users = $this->entityManager->getRepository(User::class)->findBy([], ['createdAt' => 'DESC']);
        $payload = array_map(static function (User $user): array {
            return [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'role' => $user->getRole()->value,
                'status' => $user->getStatus()->value,
                'created_at' => $user->getCreatedAt()->format(DATE_ATOM),
            ];
        }, $users);

        return $this->ok(['items' => $payload]);
    }

    #[Route('/users/{id}/role', methods: ['PATCH'])]
    public function updateRole(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'role' => [new Assert\Required([new Assert\Choice(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))])],
        ]));
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->error('User not found', 404);
        }

        $user->setRole(UserRole::from($payload['role']))->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->ok(['message' => 'Role updated']);
    }

    #[Route('/users/{id}/reset-password', methods: ['POST'])]
    public function resetPassword(int $id, Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'new_password' => [new Assert\Optional([new Assert\Length(min: 8)])],
        ], allowExtraFields: true));
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        /** @var User|null $user */
        $user = $this->entityManager->getRepository(User::class)->find($id);
        if (!$user) {
            return $this->error('User not found', 404);
        }

        $newPassword = $payload['new_password'] ?? ('Temp@' . bin2hex(random_bytes(4)));
        $user->setPassword($this->passwordHasher->hashPassword($user, $newPassword));
        $user->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->ok([
            'message' => 'Password reset successful',
            'temporary_password' => $newPassword,
        ]);
    }

    #[Route('/step-up/verify', methods: ['POST'])]
    public function verifyStepUp(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'password' => [new Assert\Required([new Assert\NotBlank()])],
            'justification' => [new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 5)])],
        ]));
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $valid = $this->stepUpAuthService->verifyStepUp($user, $payload['password'], $payload['justification']);
        if (!$valid) {
            return $this->error('Step-up verification failed', 401);
        }

        return $this->ok(['verified' => true]);
    }
}
