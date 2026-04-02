<?php

namespace App\Controller;

use App\Entity\AnalyticsFeature;
use App\Entity\AnalyticsSavedQuery;
use App\Entity\User;
use App\Repository\AnalyticsFeatureRepository;
use App\Repository\AnalyticsSavedQueryRepository;
use App\Service\AnalyticsService;
use App\Service\ApiException;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/analytics')]
class AnalyticsController extends ApiController
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
        private readonly AnalyticsSavedQueryRepository $savedQueryRepository,
        private readonly AnalyticsFeatureRepository $featureRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/query', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/analytics/query', tags: ['Analytics'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Query result')])]
    public function query(Request $request): JsonResponse
    {
        $user = $this->requireAnalyst();
        if ($user instanceof JsonResponse) {
            return $user;
        }

        try {
            return $this->ok($this->analyticsService->executeQuery(json_decode($request->getContent(), true) ?? []));
        } catch (ApiException $e) {
            return $this->fromApiException($e);
        }
    }

    #[Route('/queries/save', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/analytics/queries/save', tags: ['Analytics'], security: [['Bearer' => []]], responses: [new OA\Response(response: 201, description: 'Saved query created')])]
    public function saveQuery(Request $request): JsonResponse
    {
        $user = $this->requireAnalyst();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $payload = json_decode($request->getContent(), true) ?? [];
        $entity = (new AnalyticsSavedQuery())
            ->setName((string) ($payload['name'] ?? 'Untitled query'))
            ->setQueryDefinitionJson(json_encode($payload['query_definition'] ?? [], JSON_THROW_ON_ERROR))
            ->setCreatedBy($user)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $this->ok(['id' => $entity->getId(), 'name' => $entity->getName()], 201);
    }

    #[Route('/queries', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/analytics/queries', tags: ['Analytics'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Saved queries')])]
    public function queries(): JsonResponse
    {
        $user = $this->requireAnalyst();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $items = array_map(fn (AnalyticsSavedQuery $q) => [
            'id' => $q->getId(),
            'name' => $q->getName(),
            'query_definition' => json_decode($q->getQueryDefinitionJson(), true),
            'created_at' => $q->getCreatedAt()->format(DATE_ATOM),
        ], $this->savedQueryRepository->findBy([], ['id' => 'DESC']));

        return $this->ok(['items' => $items]);
    }

    #[Route('/features', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/analytics/features', tags: ['Analytics'], security: [['Bearer' => []]], responses: [new OA\Response(response: 201, description: 'Feature created')])]
    public function createFeature(Request $request): JsonResponse
    {
        $user = $this->requireAnalyst();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $payload = json_decode($request->getContent(), true) ?? [];
        $feature = (new AnalyticsFeature())
            ->setName((string) ($payload['name'] ?? 'Untitled feature'))
            ->setDefinitionJson(json_encode($payload['definition'] ?? [], JSON_THROW_ON_ERROR))
            ->setCreatedBy($user)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($feature);
        $this->entityManager->flush();

        return $this->ok(['id' => $feature->getId(), 'name' => $feature->getName()], 201);
    }

    #[Route('/features', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/analytics/features', tags: ['Analytics'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Features list')])]
    public function features(): JsonResponse
    {
        $user = $this->requireAnalyst();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $items = array_map(fn (AnalyticsFeature $f) => [
            'id' => $f->getId(),
            'name' => $f->getName(),
            'definition' => json_decode($f->getDefinitionJson(), true),
            'created_at' => $f->getCreatedAt()->format(DATE_ATOM),
        ], $this->featureRepository->findBy([], ['id' => 'DESC']));
        return $this->ok(['items' => $items]);
    }

    private function requireAnalyst(): User|JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }
        if (!in_array($user->getRole()->value, ['ROLE_ANALYST', 'ROLE_SYSTEM_ADMIN'], true)) {
            return $this->error('Analyst role required', 403);
        }

        return $user;
    }
}
