<?php

namespace App\Controller;

use App\Entity\OrgUnit;
use App\Repository\OrgUnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin/org-units')]
class AdminOrgUnitController extends ApiController
{
    public function __construct(private readonly OrgUnitRepository $orgUnitRepository, private readonly EntityManagerInterface $entityManager)
    {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/admin/org-units', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Org unit list')])]
    public function list(): JsonResponse
    {
        return $this->ok(['items' => array_map(fn (OrgUnit $u) => $this->toArray($u), $this->orgUnitRepository->findBy([], ['name' => 'ASC']))]);
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/admin/org-units', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 201, description: 'Org unit created')])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $parent = isset($payload['parent_id']) ? $this->orgUnitRepository->find((int) $payload['parent_id']) : null;
        $entity = (new OrgUnit())
            ->setName((string) ($payload['name'] ?? ''))
            ->setParent($parent)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
        return $this->ok($this->toArray($entity), 201);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    #[OA\Patch(path: '/api/v1/admin/org-units/{id}', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Org unit updated')])]
    public function patch(int $id, Request $request): JsonResponse
    {
        $entity = $this->orgUnitRepository->find($id);
        if (!$entity) {
            return $this->error('Org unit not found', 404);
        }
        $payload = json_decode($request->getContent(), true) ?? [];
        if (isset($payload['name'])) {
            $entity->setName((string) $payload['name']);
        }
        if (array_key_exists('parent_id', $payload)) {
            $entity->setParent($payload['parent_id'] ? $this->orgUnitRepository->find((int) $payload['parent_id']) : null);
        }
        $this->entityManager->flush();
        return $this->ok($this->toArray($entity));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/v1/admin/org-units/{id}', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Org unit deleted')])]
    public function delete(int $id): JsonResponse
    {
        $entity = $this->orgUnitRepository->find($id);
        if (!$entity) {
            return $this->error('Org unit not found', 404);
        }
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
        return $this->ok(['message' => 'Org unit deleted']);
    }

    private function toArray(OrgUnit $unit): array
    {
        return [
            'id' => $unit->getId(),
            'name' => $unit->getName(),
            'parent_id' => $unit->getParent()?->getId(),
            'created_at' => $unit->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
