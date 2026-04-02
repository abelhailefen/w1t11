<?php

namespace App\Controller;

use App\Entity\CredentialSubmission;
use App\Entity\CredentialVersion;
use App\Entity\User;
use App\Enum\CredentialState;
use App\Repository\CredentialFileRepository;
use App\Repository\CredentialSubmissionRepository;
use App\Service\ApiException;
use App\Service\CredentialWorkflowService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/credentials')]
class CredentialController extends ApiController
{
    public function __construct(
        private readonly CredentialSubmissionRepository $submissionRepository,
        private readonly CredentialFileRepository $credentialFileRepository,
        private readonly CredentialWorkflowService $workflowService,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/credentials', tags: ['Credentials'], security: [['Bearer' => []]], responses: [new OA\Response(response: 201, description: 'Created')])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'practitioner_id' => [new Assert\Required([new Assert\Positive()])],
            'payload' => [new Assert\Optional([new Assert\Type('array')])],
        ], allowExtraFields: true));
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        try {
            $submission = $this->workflowService->createSubmission((int) $payload['practitioner_id'], $user, $payload['payload'] ?? []);
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        return $this->ok($this->submissionToArray($submission), 201);
    }

    #[Route('/{id}/submit', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/credentials/{id}/submit', tags: ['Credentials'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Submitted')])]
    public function submit(int $id, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $submission = $this->submissionRepository->find($id);
        if (!$submission) {
            return $this->error('Credential submission not found', 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        try {
            $updated = $this->workflowService->submit($submission, $user, $payload['payload'] ?? []);
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        return $this->ok($this->submissionToArray($updated));
    }

    #[Route('/{id}/start-review', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/credentials/{id}/start-review', tags: ['Credentials'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Under review')])]
    public function startReview(int $id): JsonResponse
    {
        return $this->transitionWithoutBody($id, fn (CredentialSubmission $s, User $u) => $this->workflowService->startReview($s, $u));
    }

    #[Route('/{id}/approve', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/credentials/{id}/approve', tags: ['Credentials'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Approved')])]
    public function approve(int $id): JsonResponse
    {
        return $this->transitionWithoutBody($id, fn (CredentialSubmission $s, User $u) => $this->workflowService->approve($s, $u));
    }

    #[Route('/{id}/reject', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/credentials/{id}/reject', tags: ['Credentials'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Rejected')])]
    public function reject(int $id, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $submission = $this->submissionRepository->find($id);
        if (!$submission) {
            return $this->error('Credential submission not found', 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'comment' => [new Assert\Required([new Assert\NotBlank()])],
        ], allowExtraFields: true));
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        try {
            $updated = $this->workflowService->reject($submission, $user, (string) $payload['comment']);
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        return $this->ok($this->submissionToArray($updated));
    }

    #[Route('/{id}/request-resubmission', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/credentials/{id}/request-resubmission', tags: ['Credentials'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Resubmission requested')])]
    public function requestResubmission(int $id): JsonResponse
    {
        return $this->transitionWithoutBody($id, fn (CredentialSubmission $s, User $u) => $this->workflowService->requestResubmission($s, $u));
    }

    #[Route('/{id}/versions', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/credentials/{id}/versions', tags: ['Credentials'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Version history')])]
    public function versions(int $id): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $submission = $this->submissionRepository->find($id);
        if (!$submission) {
            return $this->error('Credential submission not found', 404);
        }

        try {
            $versions = $this->workflowService->versions($submission, $user);
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        return $this->ok([
            'submission' => $this->submissionToArray($submission),
            'items' => array_map(fn (CredentialVersion $version) => $this->versionToArray($version), $versions),
        ]);
    }

    #[Route('/{id}/rollback', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/credentials/{id}/rollback', tags: ['Credentials'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Rollback successful')])]
    public function rollback(int $id, Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $submission = $this->submissionRepository->find($id);
        if (!$submission) {
            return $this->error('Credential submission not found', 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'target_version_no' => [new Assert\Required([new Assert\Positive()])],
            'password' => [new Assert\Required([new Assert\NotBlank()])],
            'justification' => [new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 5)])],
        ], allowExtraFields: true));
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        try {
            $updated = $this->workflowService->rollback(
                $submission,
                (int) $payload['target_version_no'],
                $user,
                (string) $payload['password'],
                (string) $payload['justification']
            );
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        return $this->ok($this->submissionToArray($updated));
    }

    #[Route('/queue', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/credentials/queue', tags: ['Credentials'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Queue list')])]
    public function queue(Request $request): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $stateParam = $request->query->get('state');
        $state = null;
        if (is_string($stateParam) && $stateParam !== '') {
            try {
                $state = CredentialState::from($stateParam);
            } catch (\ValueError) {
                return $this->error('Invalid state filter', 400);
            }
        }

        $items = $this->workflowService->queue($state, $user);
        return $this->ok(['items' => array_map(fn (CredentialSubmission $item) => $this->submissionToArray($item), $items)]);
    }

    private function transitionWithoutBody(int $id, callable $transition): JsonResponse
    {
        $user = $this->requireUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $submission = $this->submissionRepository->find($id);
        if (!$submission) {
            return $this->error('Credential submission not found', 404);
        }

        try {
            /** @var CredentialSubmission $updated */
            $updated = $transition($submission, $user);
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        return $this->ok($this->submissionToArray($updated));
    }

    private function requireUser(): User|JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        return $user;
    }

    private function submissionToArray(CredentialSubmission $submission): array
    {
        return [
            'id' => $submission->getId(),
            'practitioner' => [
                'id' => $submission->getPractitioner()->getId(),
                'full_name' => $submission->getPractitioner()->getFullName(),
                'firm' => [
                    'id' => $submission->getPractitioner()->getFirm()->getId(),
                    'name' => $submission->getPractitioner()->getFirm()->getName(),
                ],
            ],
            'current_state' => $submission->getCurrentState()->value,
            'created_by' => [
                'id' => $submission->getCreatedBy()->getId(),
                'username' => $submission->getCreatedBy()->getUsername(),
            ],
            'created_at' => $submission->getCreatedAt()->format(DATE_ATOM),
            'updated_at' => $submission->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function versionToArray(CredentialVersion $version): array
    {
        $files = $this->credentialFileRepository->findByVersion($version);

        return [
            'id' => $version->getId(),
            'version_no' => $version->getVersionNo(),
            'state' => $version->getState()->value,
            'payload_json' => json_decode($version->getPayloadJson(), true),
            'rejection_comment' => $version->getRejectionComment(),
            'created_by' => [
                'id' => $version->getCreatedBy()->getId(),
                'username' => $version->getCreatedBy()->getUsername(),
            ],
            'created_at' => $version->getCreatedAt()->format(DATE_ATOM),
            'files' => array_map(static fn ($file) => [
                'id' => $file->getId(),
                'original_name' => $file->getOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size_bytes' => $file->getSizeBytes(),
                'uploaded_at' => $file->getUploadedAt()->format(DATE_ATOM),
            ], $files),
        ];
    }
}
