<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

class QuestionSimilarityService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function checkDuplicates(int $questionVersionId): array
    {
        $current = $this->connection->fetchAssociative(
            'SELECT qv.id, qv.question_id, qv.plain_text_index FROM question_versions qv WHERE qv.id = :id',
            ['id' => $questionVersionId]
        );
        if (!$current) {
            throw new ApiException('Question version not found', 404);
        }

        $threshold = (float) ($_ENV['DUPLICATE_SIMILARITY_THRESHOLD'] ?? $_SERVER['DUPLICATE_SIMILARITY_THRESHOLD'] ?? 80);
        $published = $this->connection->fetchAllAssociative(
            'SELECT q.id as question_id, qv.id as version_id, qv.plain_text_index, qv.content_html
             FROM questions q
             INNER JOIN question_versions qv ON q.current_version_id = qv.id
             WHERE q.status = :status AND q.id != :qid',
            ['status' => 'PUBLISHED', 'qid' => (int) $current['question_id']]
        );

        $flags = [];
        foreach ($published as $candidate) {
            similar_text((string) $current['plain_text_index'], (string) $candidate['plain_text_index'], $score);
            if ($score < $threshold) {
                continue;
            }

            $this->connection->insert('question_similarity_flags', [
                'question_version_id' => $questionVersionId,
                'matched_question_id' => (int) $candidate['question_id'],
                'similarity_score' => round($score, 2),
                'threshold' => $threshold,
                'status' => 'pending',
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $flags[] = [
                'matched_question_id' => (int) $candidate['question_id'],
                'similarity_score' => round($score, 2),
                'content_preview' => mb_substr(strip_tags((string) $candidate['content_html']), 0, 180),
            ];
        }

        return $flags;
    }
}
