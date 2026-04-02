<?php

namespace App\Controller;

use App\Entity\QuestionCategory;
use App\Repository\QuestionCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin/question-categories')]
class AdminQuestionCategoryController extends ApiController
{
    public function __construct(private readonly QuestionCategoryRepository $repo, private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/admin/question-categories', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Category list')])]
    public function list(): JsonResponse
    {
        return $this->ok(['items' => array_map(fn (QuestionCategory $c) => $this->toArray($c), $this->repo->findBy([], ['name' => 'ASC']))]);
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/admin/question-categories', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 201, description: 'Category created')])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $parent = isset($payload['parent_id']) ? $this->repo->find((int) $payload['parent_id']) : null;
        $entity = (new QuestionCategory())
            ->setName((string) ($payload['name'] ?? ''))
            ->setDescription($payload['description'] ?? null)
            ->setParent($parent)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($entity);
        $this->em->flush();
        return $this->ok($this->toArray($entity), 201);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    #[OA\Patch(path: '/api/v1/admin/question-categories/{id}', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Category updated')])]
    public function update(int $id, Request $request): JsonResponse
    {
        $entity = $this->repo->find($id);
        if (!$entity) {
            return $this->error('Category not found', 404);
        }
        $payload = json_decode($request->getContent(), true) ?? [];
        if (isset($payload['name'])) {
            $entity->setName((string) $payload['name']);
        }
        if (array_key_exists('description', $payload)) {
            $entity->setDescription($payload['description']);
        }
        if (array_key_exists('parent_id', $payload)) {
            $entity->setParent($payload['parent_id'] ? $this->repo->find((int) $payload['parent_id']) : null);
        }
        $this->em->flush();
        return $this->ok($this->toArray($entity));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/v1/admin/question-categories/{id}', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Category deleted')])]
    public function delete(int $id): JsonResponse
    {
        $entity = $this->repo->find($id);
        if (!$entity) {
            return $this->error('Category not found', 404);
        }
        $this->em->remove($entity);
        $this->em->flush();
        return $this->ok(['message' => 'Category deleted']);
    }

    private function toArray(QuestionCategory $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
            'parent_id' => $category->getParent()?->getId(),
            'created_at' => $category->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
