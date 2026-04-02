<?php

namespace App\Controller;

use App\Entity\Firm;
use App\Enum\FirmStatus;
use App\Repository\FirmRepository;
use OpenApi\Attributes as OA;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/admin/firms')]
class AdminFirmController extends ApiController
{
    public function __construct(
        private readonly FirmRepository $firmRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/admin/firms', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Firm list')])]
    public function list(): JsonResponse
    {
        $items = array_map(fn (Firm $firm) => $this->toArray($firm), $this->firmRepository->findBy([], ['name' => 'ASC']));
        return $this->ok(['items' => $items]);
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/admin/firms', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 201, description: 'Firm created')])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'name' => [new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 2, max: 255)])],
            'address' => [new Assert\Optional([new Assert\Length(max: 500)])],
            'status' => [new Assert\Optional([new Assert\Choice(array_map(fn (FirmStatus $s) => $s->value, FirmStatus::cases()))])],
        ], allowExtraFields: true));
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        if ($this->firmRepository->findOneBy(['name' => $payload['name']])) {
            return $this->error('Firm name already exists', 409);
        }

        $firm = (new Firm())
            ->setName($payload['name'])
            ->setAddress($payload['address'] ?? null)
            ->setStatus(isset($payload['status']) ? FirmStatus::from($payload['status']) : FirmStatus::ACTIVE)
            ->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($firm);
        $this->entityManager->flush();

        return $this->ok($this->toArray($firm), 201);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    #[OA\Patch(path: '/api/v1/admin/firms/{id}', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Firm updated')])]
    public function patch(int $id, Request $request): JsonResponse
    {
        $firm = $this->firmRepository->find($id);
        if (!$firm) {
            return $this->error('Firm not found', 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'name' => [new Assert\Optional([new Assert\Length(min: 2, max: 255)])],
            'address' => [new Assert\Optional([new Assert\Length(max: 500)])],
            'status' => [new Assert\Optional([new Assert\Choice(array_map(fn (FirmStatus $s) => $s->value, FirmStatus::cases()))])],
        ], allowMissingFields: true, allowExtraFields: true));
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        if (isset($payload['name'])) {
            $firm->setName($payload['name']);
        }
        if (array_key_exists('address', $payload)) {
            $firm->setAddress($payload['address']);
        }
        if (isset($payload['status'])) {
            $firm->setStatus(FirmStatus::from($payload['status']));
        }

        $this->entityManager->flush();
        return $this->ok($this->toArray($firm));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/v1/admin/firms/{id}', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Firm deactivated')])]
    public function delete(int $id): JsonResponse
    {
        $firm = $this->firmRepository->find($id);
        if (!$firm) {
            return $this->error('Firm not found', 404);
        }

        $firm->setStatus(FirmStatus::INACTIVE);
        $this->entityManager->flush();

        return $this->ok(['message' => 'Firm deactivated']);
    }

    private function toArray(Firm $firm): array
    {
        return [
            'id' => $firm->getId(),
            'name' => $firm->getName(),
            'address' => $firm->getAddress(),
            'status' => $firm->getStatus()->value,
            'created_at' => $firm->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
