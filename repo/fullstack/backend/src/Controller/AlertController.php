<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AlertService;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/alerts')]
class AlertController extends ApiController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly AlertService $alertService
    ) {
    }

    #[Route('', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/alerts', tags: ['Audit'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Alerts list')])]
    public function list(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }
        if ($user->getRole()->value !== 'ROLE_SYSTEM_ADMIN') {
            return $this->error('System admin role required', 403);
        }

        $where = ['1=1'];
        $params = [];
        if ($request->query->get('severity')) {
            $where[] = 'severity = :severity';
            $params['severity'] = (string) $request->query->get('severity');
        }
        if ($request->query->has('acknowledged')) {
            $ack = filter_var($request->query->get('acknowledged'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($ack === true) {
                $where[] = 'acknowledged_at IS NOT NULL';
            } elseif ($ack === false) {
                $where[] = 'acknowledged_at IS NULL';
            }
        }

        $items = $this->connection->fetchAllAssociative('SELECT * FROM alerts WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC', $params);
        return $this->ok(['items' => $items]);
    }

    #[Route('/{id}/acknowledge', methods: ['POST'])]
    #[OA\Post(path: '/api/v1/alerts/{id}/acknowledge', tags: ['Audit'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Alert acknowledged')])]
    public function acknowledge(int $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }
        if ($user->getRole()->value !== 'ROLE_SYSTEM_ADMIN') {
            return $this->error('System admin role required', 403);
        }

        if (!$this->alertService->acknowledge($id, (int) $user->getId())) {
            return $this->error('Alert not found', 404);
        }

        return $this->ok(['message' => 'Alert acknowledged']);
    }
}
