<?php

namespace App\Controller;

use App\Entity\User;
use OpenApi\Attributes as OA;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/audit')]
class AuditController extends ApiController
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route('/logs', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/audit/logs', tags: ['Audit'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Audit logs')])]
    public function logs(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }
        if ($user->getRole()->value !== 'ROLE_SYSTEM_ADMIN') {
            return $this->error('System admin role required', 403);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $where = ['1=1'];
        $params = [];
        if ($request->query->get('action_type')) {
            $where[] = 'action_type = :action';
            $params['action'] = (string) $request->query->get('action_type');
        }
        if ($request->query->get('entity_type')) {
            $where[] = 'entity_type = :entityType';
            $params['entityType'] = (string) $request->query->get('entity_type');
        }
        if ($request->query->getInt('user_id') > 0) {
            $where[] = 'user_id = :userId';
            $params['userId'] = $request->query->getInt('user_id');
        }

        $total = (int) $this->connection->fetchOne('SELECT COUNT(id) FROM audit_logs WHERE ' . implode(' AND ', $where), $params);
        $items = $this->connection->fetchAllAssociative('SELECT id, occurred_at, user_id, action_type, entity_type, entity_id, old_value_json, new_value_json, ip_address, retention_expires_at FROM audit_logs WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset, $params);

        return $this->ok(['items' => $items, 'pagination' => ['page' => $page, 'limit' => $limit, 'total' => $total]]);
    }
}
