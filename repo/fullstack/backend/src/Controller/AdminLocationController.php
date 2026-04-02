<?php

namespace App\Controller;

use App\Entity\Location;
use App\Enum\LocationStatus;
use App\Repository\LocationRepository;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/v1/admin/locations')]
class AdminLocationController extends ApiController
{
    public function __construct(
        private readonly LocationRepository $locationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/admin/locations', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Location list')])]
    public function list(): JsonResponse
    {
        return $this->ok(['items' => array_map(fn (Location $l) => $this->toArray($l), $this->locationRepository->findBy([], ['name' => 'ASC']))]);
    }

    #[Route('', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/admin/locations', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 201, description: 'Location created')])]
    public function create(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $violations = $this->validator->validate($payload, new Assert\Collection([
            'name' => [new Assert\Required([new Assert\NotBlank(), new Assert\Length(min: 2, max: 255)])],
            'address' => [new Assert\Optional([new Assert\Length(max: 500)])],
            'capacity' => [new Assert\Optional([new Assert\Positive()])],
        ], allowExtraFields: true));
        if (count($violations) > 0) {
            return $this->validationError($violations);
        }

        $location = (new Location())
            ->setName($payload['name'])
            ->setAddress($payload['address'] ?? null)
            ->setCapacity((int) ($payload['capacity'] ?? 1))
            ->setStatus(LocationStatus::ACTIVE)
            ->setCreatedAt(new \DateTimeImmutable());
        $this->entityManager->persist($location);
        $this->entityManager->flush();

        return $this->ok($this->toArray($location), 201);
    }

    #[Route('/{id}', methods: ['PATCH'])]
    #[OA\Patch(path: '/api/v1/admin/locations/{id}', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Location updated')])]
    public function update(int $id, Request $request): JsonResponse
    {
        $location = $this->locationRepository->find($id);
        if (!$location) {
            return $this->error('Location not found', 404);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        if (isset($payload['name'])) {
            $location->setName((string) $payload['name']);
        }
        if (array_key_exists('address', $payload)) {
            $location->setAddress($payload['address']);
        }
        if (isset($payload['capacity'])) {
            $location->setCapacity((int) $payload['capacity']);
        }
        if (isset($payload['status'])) {
            $location->setStatus(LocationStatus::from((string) $payload['status']));
        }

        $this->entityManager->flush();
        return $this->ok($this->toArray($location));
    }

    #[Route('/{id}', methods: ['DELETE'])]
    #[OA\Delete(path: '/api/v1/admin/locations/{id}', tags: ['Admin'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Location deactivated')])]
    public function deactivate(int $id): JsonResponse
    {
        $location = $this->locationRepository->find($id);
        if (!$location) {
            return $this->error('Location not found', 404);
        }
        $location->setStatus(LocationStatus::INACTIVE);
        $this->entityManager->flush();
        return $this->ok(['message' => 'Location deactivated']);
    }

    private function toArray(Location $location): array
    {
        return [
            'id' => $location->getId(),
            'name' => $location->getName(),
            'address' => $location->getAddress(),
            'capacity' => $location->getCapacity(),
            'status' => $location->getStatus()->value,
            'created_at' => $location->getCreatedAt()->format(DATE_ATOM),
        ];
    }
}
