<?php

namespace App\Controller;

use App\Entity\QuestionTag;
use App\Repository\QuestionTagRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/admin/question-tags')]
class AdminQuestionTagController extends ApiController
{
    public function __construct(private readonly QuestionTagRepository $repo, private readonly EntityManagerInterface $em)
    {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/admin/question-tags', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Tag list')])]
    public function list(): JsonResponse
    {
        return $this->ok(['items' => array_map(fn (QuestionTag $t) => ['id' => $t->getId(), 'name' => $t->getName()], $this->repo->findBy([], ['name' => 'ASC']))]);
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/admin/question-tags', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 201, description: 'Tag created')])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $tag = (new QuestionTag())->setName((string) ($payload['name'] ?? ''))->setCreatedAt(new \DateTimeImmutable());
        $this->em->persist($tag);
        $this->em->flush();
        return $this->ok(['id' => $tag->getId(), 'name' => $tag->getName()], 201);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    #[OA\Patch(path: '/api/v1/admin/question-tags/{id}', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Tag updated')])]
    public function update(int $id, Request $request): JsonResponse
    {
        $tag = $this->repo->find($id);
        if (!$tag) {
            return $this->error('Tag not found', 404);
        }
        $tag->setName((string) ((json_decode($request->getContent(), true) ?? [])['name'] ?? $tag->getName()));
        $this->em->flush();
        return $this->ok(['id' => $tag->getId(), 'name' => $tag->getName()]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/v1/admin/question-tags/{id}', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Tag deleted')])]
    public function delete(int $id): JsonResponse
    {
        $tag = $this->repo->find($id);
        if (!$tag) {
            return $this->error('Tag not found', 404);
        }
        $this->em->remove($tag);
        $this->em->flush();
        return $this->ok(['message' => 'Tag deleted']);
    }
}
