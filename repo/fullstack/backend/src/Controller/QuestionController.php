<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ApiException;
use App\Service\QuestionImportExportService;
use App\Service\QuestionService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/v1/questions')]
#[IsGranted('ROLE_CONTENT_ADMIN')]
class QuestionController extends ApiController
{
    public function __construct(
        private readonly QuestionService $questionService,
        private readonly QuestionImportExportService $importExportService
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/questions', tags: ['Questions'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Questions list')])]
    public function list(Request $request): JsonResponse
    {
        return $this->ok($this->questionService->list($request->query->all()));
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/questions', tags: ['Questions'], security: [['Bearer' => []]], responses: [new OA\Response(response: 201, description: 'Question created')])]
    public function create(Request $request): JsonResponse
    {
        $user = $this->requireManager();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $payload = json_decode($request->getContent(), true) ?? [];
        try {
            return $this->ok($this->questionService->create($payload, $user), 201);
        } catch (ApiException $e) {
            return $this->fromApiException($e);
        }
    }

    #[Route('/{id}', methods: ['GET'], requirements: ['id' => '\\d+'])]
    #[OA\Get(path: '/api/v1/questions/{id}', tags: ['Questions'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Question detail')])]
    public function detail(int $id): JsonResponse
    {
        try {
            return $this->ok($this->questionService->detail($id));
        } catch (ApiException $e) {
            return $this->fromApiException($e);
        }
    }

    #[Route('/{id}', methods: ['PATCH'], requirements: ['id' => '\\d+'])]
    #[OA\Patch(path: '/api/v1/questions/{id}', tags: ['Questions'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Question updated')])]
    public function update(int $id, Request $request): JsonResponse
    {
        $user = $this->requireManager();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        try {
            return $this->ok($this->questionService->update($id, json_decode($request->getContent(), true) ?? [], $user));
        } catch (ApiException $e) {
            return $this->fromApiException($e);
        }
    }

    #[Route('/{id}/publish', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[OA\Post(path: '/api/v1/questions/{id}/publish', tags: ['Questions'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Publish result')])]
    public function publish(int $id, Request $request): JsonResponse
    {
        $user = $this->requireManager();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $payload = json_decode($request->getContent(), true) ?? [];
        try {
            return $this->ok($this->questionService->publish($id, $user, (bool) ($payload['ack_duplicates'] ?? false)));
        } catch (ApiException $e) {
            return $this->fromApiException($e);
        }
    }

    #[Route('/{id}/status', methods: ['PATCH'], requirements: ['id' => '\\d+'])]
    #[OA\Patch(path: '/api/v1/questions/{id}/status', tags: ['Questions'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Status changed')])]
    public function status(int $id, Request $request): JsonResponse
    {
        $user = $this->requireManager();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $payload = json_decode($request->getContent(), true) ?? [];
        try {
            return $this->ok($this->questionService->changeStatus($id, (string) ($payload['status'] ?? '')));
        } catch (ApiException $e) {
            return $this->fromApiException($e);
        }
    }

    #[Route('/import', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/questions/import', tags: ['Questions'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Import result')])]
    public function import(Request $request): JsonResponse
    {
        $user = $this->requireManager();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $file = $request->files->get('file');
        if (!$file) {
            return $this->error('File is required', 400);
        }
        try {
            return $this->ok($this->importExportService->importFromFile($file, $user));
        } catch (ApiException $e) {
            return $this->fromApiException($e);
        }
    }

    #[Route('/export', methods: ['GET'], priority: 20)]
    #[OA\Get(path: '/api/v1/questions/export', tags: ['Questions'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Export file')])]
    public function export(Request $request): BinaryFileResponse|JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $format = (string) $request->query->get('format', 'csv');
        if (!in_array($format, ['csv', 'xlsx'], true)) {
            return $this->error('Invalid format', 400);
        }
        try {
            $result = $this->importExportService->exportToFile($request->query->all(), $format, $user?->getId());
        } catch (ApiException $e) {
            return $this->fromApiException($e);
        }
        $response = new BinaryFileResponse($result['path']);
        $response->headers->set('Content-Type', $result['mime']);
        $response->setContentDisposition('attachment', $result['filename']);
        return $response;
    }

    #[Route('/{id}/versions', methods: ['GET'], requirements: ['id' => '\\d+'])]
    #[OA\Get(path: '/api/v1/questions/{id}/versions', tags: ['Questions'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Version history')])]
    public function versions(int $id): JsonResponse
    {
        return $this->ok(['items' => $this->questionService->versions($id)]);
    }

    #[Route('/{id}/rollback', methods: ['POST'], requirements: ['id' => '\\d+'])]
    #[OA\Post(path: '/api/v1/questions/{id}/rollback', tags: ['Questions'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Rollback complete')])]
    public function rollback(int $id, Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }
        $payload = json_decode($request->getContent(), true) ?? [];
        try {
            return $this->ok($this->questionService->rollback($id, (int) ($payload['target_version_no'] ?? 0), $user, (string) ($payload['password'] ?? ''), (string) ($payload['justification'] ?? '')));
        } catch (ApiException $e) {
            return $this->fromApiException($e);
        }
    }

    private function requireManager(): User|JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }
        if (!in_array($user->getRole()->value, ['ROLE_CONTENT_ADMIN', 'ROLE_SYSTEM_ADMIN'], true)) {
            return $this->error('Content admin role required', 403);
        }
        return $user;
    }
}
