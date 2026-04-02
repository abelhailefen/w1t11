<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use OpenApi\Attributes as OA;

class HealthController extends AbstractController
{
    #[Route('/api/v1/health', name: 'api_health', methods: ['GET'])]
    #[OA\Get(
        path: '/api/v1/health',
        summary: 'Health check',
        description: 'Returns backend service health status.',
        tags: ['Health'],
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Service is healthy',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'status', type: 'string', example: 'ok'),
                        new OA\Property(property: 'service', type: 'string', example: 'backend'),
                        new OA\Property(property: 'timestamp', type: 'string', format: 'date-time')
                    ]
                )
            )
        ]
    )]
    public function health(): JsonResponse
    {
        return $this->json([
            'status' => 'ok',
            'service' => 'backend',
            'timestamp' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
