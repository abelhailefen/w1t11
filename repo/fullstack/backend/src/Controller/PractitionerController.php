<?php

namespace App\Controller;

use App\Entity\CredentialFile;
use App\Entity\Practitioner;
use App\Entity\User;
use App\Enum\PractitionerStatus;
use App\Repository\CredentialFileRepository;
use App\Repository\PractitionerRepository;
use App\Service\ApiException;
use App\Service\PractitionerService;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/practitioners')]
class PractitionerController extends ApiController
{
    public function __construct(
        private readonly PractitionerRepository $practitionerRepository,
        private readonly CredentialFileRepository $credentialFileRepository,
        private readonly PractitionerService $practitionerService,
        private readonly Connection $connection,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/practitioners', tags: ['Practitioners'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Practitioner list')])]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $name = $request->query->get('name');
        $status = $request->query->get('status');
        $firmId = $request->query->getInt('firm_id') ?: null;

        $payload = $this->practitionerService->list($page, $limit, $name, $status, $firmId);
        return $this->ok($payload);
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/practitioners', tags: ['Practitioners'], security: [['Bearer' => []]], responses: [new OA\Response(response: 201, description: 'Created')])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->requireAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'full_name' => [new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 2, max: 255)])],
            'firm_id' => [new Assert\Required([new Assert\Positive()])],
            'license_number' => [new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 4, max: 120)])],
            'license_jurisdiction' => [new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 2, max: 120)])],
            'contact_email' => [new Assert\Optional([new Assert\Email()])],
            'contact_phone' => [new Assert\Optional([new Assert\Length(max: 80)])],
            'status' => [new Assert\Optional([new Assert\Choice(array_map(fn (PractitionerStatus $s) => $s->value, PractitionerStatus::cases()))])],
        ], allowExtraFields: true));
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        try {
            $practitioner = $this->practitionerService->create($payload, $user);
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        return $this->ok($this->practitionerService->toMaskedArray($practitioner), 201);
    }

    #[Route('/{id}', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/practitioners/{id}', tags: ['Practitioners'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Practitioner detail')])]
    public function getOne(int $id): JsonResponse
    {
        $practitioner = $this->practitionerRepository->find($id);
        if (!$practitioner) {
            return $this->error('Practitioner not found', 404);
        }

        return $this->ok($this->practitionerService->toMaskedArray($practitioner));
    }

    #[Route('/{id}', methods: ['PATCH'])]
    #[OA\Patch(path: '/api/v1/practitioners/{id}', tags: ['Practitioners'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Updated')])]
    public function patch(int $id, Request $request): JsonResponse
    {
        $practitioner = $this->practitionerRepository->find($id);
        if (!$practitioner) {
            return $this->error('Practitioner not found', 404);
        }

        $user = $this->requireAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->canManagePractitioner($practitioner, $user)) {
            return $this->error('Forbidden', 403);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'full_name' => [new Assert\Optional([new Assert\Length(min: 2, max: 255)])],
            'firm_id' => [new Assert\Optional([new Assert\Positive()])],
            'license_number' => [new Assert\Optional([new Assert\Length(min: 4, max: 120)])],
            'license_jurisdiction' => [new Assert\Optional([new Assert\Length(min: 2, max: 120)])],
            'contact_email' => [new Assert\Optional([new Assert\Email()])],
            'contact_phone' => [new Assert\Optional([new Assert\Length(max: 80)])],
            'status' => [new Assert\Optional([new Assert\Choice(array_map(fn (PractitionerStatus $s) => $s->value, PractitionerStatus::cases()))])],
        ], allowMissingFields: true, allowExtraFields: true));
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        try {
            $updated = $this->practitionerService->update($practitioner, $payload);
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        return $this->ok($this->practitionerService->toMaskedArray($updated));
    }

    #[Route('/{id}/license/reveal', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/practitioners/{id}/license/reveal', tags: ['Practitioners'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Revealed')])]
    public function reveal(int $id, Request $request): JsonResponse
    {
        $practitioner = $this->practitionerRepository->find($id);
        if (!$practitioner) {
            return $this->error('Practitioner not found', 404);
        }

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $reason = trim((string) ($payload['reason'] ?? 'Operational need'));
        if ($reason === '') {
            $reason = 'Operational need';
        }

        try {
            $data = $this->practitionerService->revealLicense($practitioner, $user, $reason, $request->getClientIp());
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        return $this->ok($data);
    }

    #[Route('/{id}/credentials/upload', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/practitioners/{id}/credentials/upload', tags: ['Practitioners'], security: [['Bearer' => []]], responses: [new OA\Response(response: 201, description: 'Uploaded')])]
    public function upload(int $id, Request $request): JsonResponse
    {
        $practitioner = $this->practitionerRepository->find($id);
        if (!$practitioner) {
            return $this->error('Practitioner not found', 404);
        }

        $user = $this->requireAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->canAccessPractitionerCredentials($practitioner, $user)) {
            return $this->error('Forbidden', 403);
        }

        $file = $request->files->get('file');
        if (!$file) {
            return $this->error('File is required', 400);
        }

        try {
            $saved = $this->practitionerService->uploadCredentialFile($practitioner, $file, $user);
        } catch (ApiException $exception) {
            return $this->fromApiException($exception);
        }

        return $this->ok($this->fileToArray($saved), 201);
    }

    #[Route('/{id}/credentials', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/practitioners/{id}/credentials', tags: ['Practitioners'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Files')])]
    public function listFiles(int $id): JsonResponse
    {
        $practitioner = $this->practitionerRepository->find($id);
        if (!$practitioner) {
            return $this->error('Practitioner not found', 404);
        }

        $user = $this->requireAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->canAccessPractitionerCredentials($practitioner, $user)) {
            return $this->error('Forbidden', 403);
        }

        $files = $this->practitionerService->listCredentialFiles($practitioner);
        return $this->ok(['items' => array_map(fn (CredentialFile $f) => $this->fileToArray($f), $files)]);
    }

    #[Route('/{id}/credentials/{fileId}/download', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/practitioners/{id}/credentials/{fileId}/download', tags: ['Practitioners'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Download')])]
    public function download(int $id, int $fileId): BinaryFileResponse|JsonResponse
    {
        $practitioner = $this->practitionerRepository->find($id);
        if (!$practitioner) {
            return $this->error('Practitioner not found', 404);
        }

        $user = $this->requireAuthenticatedUser();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        if (!$this->canAccessPractitionerCredentials($practitioner, $user)) {
            return $this->error('Forbidden', 403);
        }

        $file = $this->credentialFileRepository->findOneForPractitioner($practitioner, $fileId);
        if (!$file) {
            return $this->error('File not found', 404);
        }
        if (!is_file($file->getStoragePath())) {
            return $this->error('Stored file is missing', 404);
        }

        $response = new BinaryFileResponse($file->getStoragePath());
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $file->getOriginalName());
        $response->headers->set('Content-Type', $file->getMimeType());
        return $response;
    }

    private function requireAuthenticatedUser(): User|JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }

        return $user;
    }

    private function canManagePractitioner(Practitioner $practitioner, User $user): bool
    {
        if (in_array($user->getRole()->value, ['ROLE_SYSTEM_ADMIN', 'ROLE_CREDENTIAL_REVIEWER'], true)) {
            return true;
        }

        $identity = method_exists($user, 'getUsername') ? (string) $user->getUsername() : (string) $user->getUserIdentifier();

        $ownerPractitioner = (int) $this->connection->fetchOne(
            'SELECT COUNT(p.id) FROM practitioners p INNER JOIN users u ON u.id = p.created_by WHERE p.id = :pid AND (u.username = :identity OR CAST(u.id AS CHAR) = :identity)',
            ['pid' => (int) $practitioner->getId(), 'identity' => $identity]
        );
        if ($ownerPractitioner > 0) {
            return true;
        }

        $ownerSubmission = (int) $this->connection->fetchOne(
            'SELECT COUNT(s.id) FROM credential_submissions s INNER JOIN users u ON u.id = s.created_by_id WHERE s.practitioner_id = :pid AND (u.username = :identity OR CAST(u.id AS CHAR) = :identity)',
            ['pid' => (int) $practitioner->getId(), 'identity' => $identity]
        );

        return $ownerSubmission > 0;
    }

    private function canAccessPractitionerCredentials(Practitioner $practitioner, User $user): bool
    {
        return $this->canManagePractitioner($practitioner, $user);
    }

    private function fileToArray(CredentialFile $file): array
    {
        return [
            'id' => $file->getId(),
            'original_name' => $file->getOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSizeBytes(),
            'version_no' => $file->getCredentialVersion()->getVersionNo(),
            'uploaded_at' => $file->getUploadedAt()->format(DATE_ATOM),
        ];
    }
}
