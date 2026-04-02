<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\UserRole;
use App\Service\ApiException;
use App\Service\AuthService;
use App\Service\CaptchaService;
use App\Service\HumanVerificationInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/auth')]
class AuthController extends ApiController
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly CaptchaService $captchaService,
        private readonly HumanVerificationInterface $humanVerificationService,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'username' => [new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 3, max: 180)])],
            'password' => [new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 8)])],
            'role' => [new Assert\Optional([new Assert\Choice(array_map(fn (UserRole $role) => $role->value, UserRole::cases()))])],
        ]));

        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        try {
            $user = $this->authService->register(
                $payload['username'],
                $payload['password'],
                $payload['role'] ?? UserRole::ROLE_USER->value
            );
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        return $this->ok([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'role' => $user->getRole()->value,
            'status' => $user->getStatus()->value,
        ], 201);
    }

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'username' => [new Assert\Required([new Assert\NotBlank()])],
            'password' => [new Assert\Required([new Assert\NotBlank()])],
            'captcha_token' => [new Assert\Optional([new Assert\Type('string')])],
            'captcha_answer' => [new Assert\Optional([new Assert\Type('string')])],
        ]));

        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        if (!$this->humanVerificationService->verify($payload)) {
            return $this->error('Human verification failed', 400);
        }

        try {
            $result = $this->authService->login(
                $payload['username'],
                $payload['password'],
                $payload['captcha_token'] ?? null,
                $payload['captcha_answer'] ?? null,
                $request->getClientIp()
            );
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        /** @var User $user */
        $user = $result['user'];

        return $this->ok([
            'token' => $result['token'],
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'role' => $user->getRole()->value,
                'status' => $user->getStatus()->value,
            ],
        ]);
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        return $this->ok(['message' => 'Logged out']);
    }

    #[Route('/me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        return $this->ok([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'role' => $user->getRole()->value,
            'status' => $user->getStatus()->value,
        ]);
    }

    #[Route('/captcha', methods: ['GET'])]
    public function captcha(): JsonResponse
    {
        return $this->ok($this->captchaService->generateChallenge());
    }

    #[Route('/captcha/verify', methods: ['POST'])]
    public function verifyCaptcha(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'token' => [new Assert\Required([new Assert\NotBlank()])],
            'answer' => [new Assert\Required([new Assert\NotBlank()])],
        ]));

        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $verified = $this->captchaService->verify($payload['token'], $payload['answer']);
        if (!$verified) {
            return $this->error('Invalid CAPTCHA answer', 400);
        }

        return $this->ok(['verified' => true]);
    }
}
