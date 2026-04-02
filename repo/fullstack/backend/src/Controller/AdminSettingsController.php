<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\SystemSettingService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin/settings')]
class AdminSettingsController extends ApiController
{
    public function __construct(private readonly SystemSettingService $systemSettingService)
    {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/admin/settings', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'System settings')])]
    public function getAll(): JsonResponse
    {
        return $this->ok(['items' => $this->systemSettingService->all()]);
    }

    #[Route('', methods: ['PUT'])]
    #[OA\Put(path: '/api/v1/admin/settings', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'System settings updated')])]
    public function update(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $payload = json_decode($request->getContent(), true) ?? [];
        $items = is_array($payload['items'] ?? null) ? $payload['items'] : [];
        $this->systemSettingService->bulkUpdate($items, $user?->getId());

        return $this->ok(['message' => 'Settings updated', 'items' => $this->systemSettingService->all()]);
    }
}
