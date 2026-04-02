<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Service\AuditLogService;
use App\Service\StepUpAuthService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
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
        private readonly ValidatorInterface $validator,
        private readonly AuditLogService $auditLogService
    ) {
    }

    #[Route('/users', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/admin/users',
        summary: 'List users',
        description: 'Returns all users for system administration.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User list',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer'),
                                    new OA\Property(property: 'username', type: 'string'),
                                    new OA\Property(property: 'role', type: 'string'),
                                    new OA\Property(property: 'status', type: 'string'),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time')
                                ],
                                type: 'object'
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden')
        ]
    )]
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
    #[OA\Patch(
        path: '/api/v1/admin/users/{id}/role',
        summary: 'Update user role',
        description: 'Changes role assignment for an existing user.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'User ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['role'],
                properties: [
                    new OA\Property(property: 'role', type: 'string', example: 'ROLE_ANALYST')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Role updated',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Role updated')])
            ),
            new OA\Response(response: 400, description: 'Validation failed'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found')
        ]
    )]
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
    #[OA\Post(
        path: '/api/v1/admin/users/{id}/reset-password',
        summary: 'Reset user password',
        description: 'Resets target user password and returns temporary password when auto-generated.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(name: 'id', description: 'User ID', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'new_password', type: 'string', minLength: 8)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Password reset successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Password reset successful'),
                        new OA\Property(property: 'temporary_password', type: 'string', nullable: true)
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation failed'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden'),
            new OA\Response(response: 404, description: 'User not found')
        ]
    )]
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
        /** @var User|null $actor */
        $actor = $this->getUser() instanceof User ? $this->getUser() : null;
        $this->auditLogService->log($actor?->getId(), 'PASSWORD_RESET', 'User', $user->getId(), null, ['target_username' => $user->getUsername()], $request->getClientIp());

        return $this->ok([
            'message' => 'Password reset successful',
            'temporary_password' => $newPassword,
        ]);
    }

    #[Route('/step-up/verify', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/admin/step-up/verify',
        summary: 'Verify step-up authentication',
        description: 'Revalidates current user password and records the provided justification.',
        tags: ['Admin'],
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['password', 'justification'],
                properties: [
                    new OA\Property(property: 'password', type: 'string'),
                    new OA\Property(property: 'justification', type: 'string', minLength: 5)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Step-up verified',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'verified', type: 'boolean', example: true)])
            ),
            new OA\Response(response: 400, description: 'Validation failed'),
            new OA\Response(response: 401, description: 'Unauthorized or invalid step-up credentials'),
            new OA\Response(response: 403, description: 'Forbidden')
        ]
    )]
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
