<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;

class QuestionService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly QuestionSimilarityService $similarityService,
        private readonly StepUpAuthService $stepUpAuthService
    ) {
    }

    public function list(array $filters): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['category'])) {
            $where[] = 'q.category_id = :category';
            $params['category'] = (int) $filters['category'];
        }
        if (!empty($filters['status'])) {
            $where[] = 'q.status = :status';
            $params['status'] = (string) $filters['status'];
        }
        if (!empty($filters['tag'])) {
            $where[] = 'EXISTS (SELECT 1 FROM question_tag_map qtm WHERE qtm.question_id = q.id AND qtm.tag_id = :tag)';
            $params['tag'] = (int) $filters['tag'];
        }

        $sql = 'SELECT q.id, q.status, q.created_at, q.updated_at, qc.name as category_name, qv.content_html, qv.difficulty
                FROM questions q
                INNER JOIN question_categories qc ON qc.id = q.category_id
                LEFT JOIN question_versions qv ON qv.id = q.current_version_id
                WHERE ' . implode(' AND ', $where) . ' ORDER BY q.updated_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
        $rows = $this->connection->fetchAllAssociative($sql, $params);

        foreach ($rows as &$row) {
            $row['tags'] = $this->connection->fetchFirstColumn('SELECT qt.name FROM question_tag_map qtm INNER JOIN question_tags qt ON qt.id = qtm.tag_id WHERE qtm.question_id = :qid ORDER BY qt.name ASC', ['qid' => (int) $row['id']]);
            $row['preview'] = mb_substr(strip_tags((string) ($row['content_html'] ?? '')), 0, 120);
            unset($row['content_html']);
        }

        return ['items' => $rows, 'pagination' => ['page' => $page, 'limit' => $limit]];
    }

    public function create(array $payload, User $user): array
    {
        return $this->connection->transactional(function (Connection $connection) use ($payload, $user): array {
            $now = new \DateTimeImmutable();
            $connection->insert('questions', [
                'category_id' => (int) $payload['category_id'],
                'status' => 'DRAFT',
                'current_version_id' => null,
                'created_by' => (int) $user->getId(),
                'duplicate_acknowledged' => 0,
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);
            $questionId = (int) $connection->lastInsertId();
            $versionId = $this->createVersion($questionId, (string) $payload['content_html'], (int) $payload['difficulty'], $user, $payload['metadata'] ?? []);
            $connection->update('questions', ['current_version_id' => $versionId], ['id' => $questionId]);
            $this->syncTags($questionId, $payload['tags'] ?? []);

            return $this->detail($questionId);
        });
    }

    public function update(int $id, array $payload, User $user): array
    {
        $question = $this->connection->fetchAssociative('SELECT * FROM questions WHERE id = :id', ['id' => $id]);
        if (!$question) {
            throw new ApiException('Question not found', 404);
        }

        return $this->connection->transactional(function (Connection $connection) use ($id, $payload, $user): array {
            $current = $this->detail($id);
            $content = (string) ($payload['content_html'] ?? $current['current_version']['content_html']);
            $difficulty = (int) ($payload['difficulty'] ?? $current['current_version']['difficulty']);
            $versionId = $this->createVersion($id, $content, $difficulty, $user, $payload['metadata'] ?? []);

            $updates = ['updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'), 'current_version_id' => $versionId, 'duplicate_acknowledged' => 0];
            if (isset($payload['category_id'])) {
                $updates['category_id'] = (int) $payload['category_id'];
            }
            $connection->update('questions', $updates, ['id' => $id]);
            if (isset($payload['tags']) && is_array($payload['tags'])) {
                $this->syncTags($id, $payload['tags']);
            }

            return $this->detail($id);
        });
    }

    public function publish(int $id, User $user, bool $ackDuplicates = false): array
    {
        $question = $this->connection->fetchAssociative('SELECT * FROM questions WHERE id = :id', ['id' => $id]);
        if (!$question) {
            throw new ApiException('Question not found', 404);
        }
        if ($question['status'] !== 'DRAFT') {
            throw new ApiException('Only draft questions can be published', 422);
        }
        $versionId = (int) ($question['current_version_id'] ?? 0);
        if ($versionId <= 0) {
            throw new ApiException('Question has no version', 422);
        }

        $flags = $this->similarityService->checkDuplicates($versionId);
        if (!empty($flags) && !$ackDuplicates && (int) $question['duplicate_acknowledged'] === 0) {
            return ['published' => false, 'warnings' => $flags];
        }

        $this->connection->update('questions', [
            'status' => 'PUBLISHED',
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'duplicate_acknowledged' => 1,
        ], ['id' => $id]);

        return ['published' => true, 'warnings' => $flags];
    }

    public function changeStatus(int $id, string $status): array
    {
        $question = $this->connection->fetchAssociative('SELECT * FROM questions WHERE id = :id', ['id' => $id]);
        if (!$question) {
            throw new ApiException('Question not found', 404);
        }

        $from = (string) $question['status'];
        $allowed = [
            'DRAFT' => ['PUBLISHED'],
            'PUBLISHED' => ['OFFLINE'],
            'OFFLINE' => ['DRAFT'],
        ];
        if (!in_array($status, $allowed[$from] ?? [], true)) {
            throw new ApiException(sprintf('Invalid transition from %s to %s', $from, $status), 422);
        }

        $this->connection->update('questions', ['status' => $status, 'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')], ['id' => $id]);
        return $this->detail($id);
    }

    public function versions(int $questionId): array
    {
        return $this->connection->fetchAllAssociative('SELECT qv.*, u.username FROM question_versions qv INNER JOIN users u ON u.id = qv.created_by WHERE qv.question_id = :id ORDER BY version_no DESC', ['id' => $questionId]);
    }

    public function rollback(int $questionId, int $targetVersionNo, User $user, string $password, string $justification): array
    {
        if ($user->getRole()->value !== 'ROLE_SYSTEM_ADMIN') {
            throw new ApiException('Only system admin can rollback', 403);
        }
        if (!$this->stepUpAuthService->verifyStepUp($user, $password, $justification)) {
            throw new ApiException('Step-up verification failed', 401);
        }

        $version = $this->connection->fetchAssociative('SELECT * FROM question_versions WHERE question_id = :qid AND version_no = :v', ['qid' => $questionId, 'v' => $targetVersionNo]);
        if (!$version) {
            throw new ApiException('Target version not found', 404);
        }

        $newVersionId = $this->createVersion($questionId, (string) $version['content_html'], (int) $version['difficulty'], $user, [
            'rollback_to' => $targetVersionNo,
            'justification' => $justification,
        ]);
        $this->connection->update('questions', ['current_version_id' => $newVersionId, 'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s')], ['id' => $questionId]);

        return $this->detail($questionId);
    }

    public function detail(int $id): array
    {
        $question = $this->connection->fetchAssociative('SELECT q.*, qc.name as category_name, u.username as created_by_username FROM questions q INNER JOIN question_categories qc ON qc.id = q.category_id INNER JOIN users u ON u.id = q.created_by WHERE q.id = :id', ['id' => $id]);
        if (!$question) {
            throw new ApiException('Question not found', 404);
        }
        $version = $this->connection->fetchAssociative('SELECT qv.*, u.username FROM question_versions qv INNER JOIN users u ON u.id = qv.created_by WHERE qv.id = :id', ['id' => (int) $question['current_version_id']]);
        $tags = $this->connection->fetchFirstColumn('SELECT qt.name FROM question_tag_map qtm INNER JOIN question_tags qt ON qt.id = qtm.tag_id WHERE qtm.question_id = :qid ORDER BY qt.name ASC', ['qid' => $id]);

        return [
            'id' => (int) $question['id'],
            'status' => $question['status'],
            'category' => ['id' => (int) $question['category_id'], 'name' => $question['category_name']],
            'tags' => $tags,
            'created_at' => $question['created_at'],
            'updated_at' => $question['updated_at'],
            'current_version' => $version,
        ];
    }

    private function createVersion(int $questionId, string $contentHtml, int $difficulty, User $user, array $metadata): int
    {
        if ($difficulty < 1 || $difficulty > 5) {
            throw new ApiException('Difficulty must be 1-5', 400);
        }

        $next = (int) $this->connection->fetchOne('SELECT COALESCE(MAX(version_no), 0) + 1 FROM question_versions WHERE question_id = :id', ['id' => $questionId]);
        $plain = trim(mb_strtolower(preg_replace('/\s+/', ' ', strip_tags($contentHtml)) ?? ''));
        $this->connection->insert('question_versions', [
            'question_id' => $questionId,
            'version_no' => $next,
            'content_html' => $contentHtml,
            'plain_text_index' => $plain,
            'difficulty' => $difficulty,
            'metadata_json' => json_encode($metadata, JSON_THROW_ON_ERROR),
            'created_by' => (int) $user->getId(),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
        return (int) $this->connection->lastInsertId();
    }

    private function syncTags(int $questionId, array $tagIds): void
    {
        $this->connection->delete('question_tag_map', ['question_id' => $questionId]);
        foreach ($tagIds as $tagId) {
            $this->connection->insert('question_tag_map', ['question_id' => $questionId, 'tag_id' => (int) $tagId]);
        }
    }
}
