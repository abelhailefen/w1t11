<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ApiException;
use App\Service\AuthService;
use App\Service\CaptchaService;
use App\Service\HumanVerificationInterface;
use OpenApi\Attributes as OA;
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
    #[OA\Post(
        path: '/api/v1/auth/register',
        summary: 'Register a user',
        description: 'Creates a new user account with role ROLE_USER.',
        tags: ['Auth'],
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password', 'full_name', 'firm_affiliation', 'license_number'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', minLength: 3, maxLength: 180, example: 'new_user'),
                    new OA\Property(property: 'password', type: 'string', minLength: 8, example: 'StrongPass@123'),
                    new OA\Property(property: 'full_name', type: 'string', minLength: 2, maxLength: 255, example: 'Jordan Blake'),
                    new OA\Property(property: 'firm_affiliation', type: 'string', minLength: 2, maxLength: 255, example: 'Eagle Point Legal'),
                    new OA\Property(property: 'license_number', type: 'string', minLength: 4, maxLength: 120, example: 'NY-123456')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User registered',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 9),
                        new OA\Property(property: 'username', type: 'string', example: 'new_user'),
                        new OA\Property(property: 'full_name', type: 'string', example: 'Jordan Blake'),
                        new OA\Property(property: 'firm_affiliation', type: 'string', example: 'Eagle Point Legal'),
                        new OA\Property(property: 'role', type: 'string', example: 'ROLE_USER'),
                        new OA\Property(property: 'status', type: 'string', example: 'ACTIVE')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Validation failed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 400),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation failed'),
                        new OA\Property(property: 'details', type: 'object')
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Username already exists',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'code', type: 'integer', example: 409),
                        new OA\Property(property: 'message', type: 'string', example: 'Username already exists'),
                        new OA\Property(property: 'details', type: 'object')
                    ]
                )
            )
        ]
    )]
    public function register(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'username' => [new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 3, max: 180)])],
            'password' => [new Assert\Required([
                new Assert\NotBlank(),
                new Assert\Length(min: 8),
                new Assert\Regex(
                    pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z\d]).{8,}$/',
                    message: 'Password must include uppercase, lowercase, number, and special character'
                ),
            ])],
            'full_name' => [new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 2, max: 255)])],
            'firm_affiliation' => [new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 2, max: 255)])],
            'license_number' => [new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 4, max: 120)])],
        ], allowExtraFields: true));

        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        try {
            $user = $this->authService->register(
                $payload['username'],
                $payload['password'],
                $payload['full_name'],
                $payload['firm_affiliation'],
                $payload['license_number']
            );
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        return $this->ok([
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'full_name' => $payload['full_name'],
            'firm_affiliation' => $payload['firm_affiliation'],
            'role' => $user->getRole()->value,
            'status' => $user->getStatus()->value,
        ], 201);
    }

    #[Route('/login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/auth/login',
        summary: 'Login and get JWT',
        description: 'Authenticates credentials, applies lockout policy, and returns a JWT token.',
        tags: ['Auth'],
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'admin'),
                    new OA\Property(property: 'password', type: 'string', example: 'Admin@123'),
                    new OA\Property(property: 'captcha_token', type: 'string', nullable: true),
                    new OA\Property(property: 'captcha_answer', type: 'string', nullable: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(
                            property: 'user',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'username', type: 'string', example: 'admin'),
                                new OA\Property(property: 'role', type: 'string', example: 'ROLE_SYSTEM_ADMIN'),
                                new OA\Property(property: 'status', type: 'string', example: 'ACTIVE')
                            ],
                            type: 'object'
                        )
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Validation or human verification error'),
            new OA\Response(response: 401, description: 'Invalid credentials'),
            new OA\Response(response: 403, description: 'CAPTCHA required or account disabled'),
            new OA\Response(response: 423, description: 'Account locked')
        ]
    )]
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
    #[OA\Post(
        path: '/api/v1/auth/logout',
        summary: 'Logout current user',
        description: 'Stateless logout acknowledgement endpoint.',
        tags: ['Auth'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Logout acknowledged',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string', example: 'Logged out')])
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if ($user) {
            $this->authService->logout($user, $request->getClientIp());
        }
        return $this->ok(['message' => 'Logged out']);
    }

    #[Route('/me', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/auth/me',
        summary: 'Get current user profile',
        description: 'Returns profile details for the authenticated user.',
        tags: ['Auth'],
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Current user details',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'username', type: 'string', example: 'admin'),
                        new OA\Property(property: 'role', type: 'string', example: 'ROLE_SYSTEM_ADMIN'),
                        new OA\Property(property: 'status', type: 'string', example: 'ACTIVE')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
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
    #[OA\Get(
        path: '/api/v1/auth/captcha',
        summary: 'Generate CAPTCHA challenge',
        description: 'Returns one-time CAPTCHA token and challenge image payload.',
        tags: ['Auth'],
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'CAPTCHA challenge generated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string'),
                        new OA\Property(property: 'challenge_image', type: 'string', example: 'data:image/png;base64,...'),
                        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time')
                    ]
                )
            )
        ]
    )]
    public function captcha(): JsonResponse
    {
        return $this->ok($this->captchaService->generateChallenge());
    }

    #[Route('/captcha/verify', methods: ['POST'])]
    #[OA\Post(
        path: '/api/v1/auth/captcha/verify',
        summary: 'Verify CAPTCHA response',
        description: 'Validates a submitted CAPTCHA answer against challenge token.',
        tags: ['Auth'],
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['token', 'answer'],
                properties: [
                    new OA\Property(property: 'token', type: 'string'),
                    new OA\Property(property: 'answer', type: 'string')
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'CAPTCHA verified',
                content: new OA\JsonContent(properties: [new OA\Property(property: 'verified', type: 'boolean', example: true)])
            ),
            new OA\Response(response: 400, description: 'Validation or verification failure')
        ]
    )]
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
