<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class QuestionImportExportService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly QuestionService $questionService,
        private readonly AuditLogService $auditLogService
    )
    {
    }

    public function importFromFile(UploadedFile $file, User $user): array
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (!in_array($ext, ['csv', 'xlsx'], true)) {
            throw new ApiException('Only CSV and XLSX are supported', 400);
        }

        $now = new \DateTimeImmutable();
        $this->connection->insert('question_import_jobs', [
            'initiated_by' => (int) $user->getId(),
            'file_name' => $file->getClientOriginalName(),
            'format' => $ext,
            'status' => 'processing',
            'result_json' => null,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'completed_at' => null,
        ]);
        $jobId = (int) $this->connection->lastInsertId();

        $sheet = IOFactory::load($file->getPathname())->getActiveSheet();
        $rows = $sheet->toArray();
        $results = [];
        foreach ($rows as $idx => $row) {
            if ($idx === 0) {
                continue;
            }
            $content = trim((string) ($row[0] ?? ''));
            $categoryId = (int) ($row[1] ?? 0);
            $difficulty = (int) ($row[2] ?? 0);
            if ($content === '') {
                $results[] = ['row' => $idx + 1, 'status' => 'error', 'reason' => 'Missing content'];
                continue;
            }
            if ($difficulty < 1 || $difficulty > 5) {
                $results[] = ['row' => $idx + 1, 'status' => 'error', 'reason' => 'Difficulty must be 1-5'];
                continue;
            }

            try {
                $question = $this->questionService->create([
                    'content_html' => $content,
                    'category_id' => $categoryId,
                    'difficulty' => $difficulty,
                    'tags' => [],
                ], $user);
                $results[] = ['row' => $idx + 1, 'status' => 'success', 'question_id' => $question['id']];
            } catch (\Throwable $e) {
                $results[] = ['row' => $idx + 1, 'status' => 'error', 'reason' => $e->getMessage()];
            }
        }

        $hasError = count(array_filter($results, fn (array $r) => $r['status'] === 'error')) > 0;
        $this->connection->update('question_import_jobs', [
            'status' => $hasError ? 'failed' : 'completed',
            'result_json' => json_encode($results, JSON_THROW_ON_ERROR),
            'completed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], ['id' => $jobId]);

        $this->auditLogService->log((int) $user->getId(), 'IMPORT', 'Question', null, null, ['job_id' => $jobId, 'status' => $hasError ? 'failed' : 'completed'], null);
        return ['id' => $jobId, 'status' => $hasError ? 'failed' : 'completed', 'results' => $results];
    }

    public function exportToFile(array $filters, string $format, ?int $userId = null): array
    {
        $data = $this->questionService->list($filters)['items'];
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['content', 'category', 'difficulty', 'tags', 'status', 'created_at'], null, 'A1');
        $row = 2;
        foreach ($data as $item) {
            $sheet->fromArray([
                $item['preview'],
                $item['category_name'],
                $item['difficulty'],
                implode(',', $item['tags']),
                $item['status'],
                $item['created_at'],
            ], null, 'A' . $row);
            $row++;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'qexp_');
        if ($format === 'xlsx') {
            $path = $tmp . '.xlsx';
            (new Xlsx($spreadsheet))->save($path);
            $this->auditLogService->log($userId, 'EXPORT', 'Question', null, null, ['format' => 'xlsx'], null);
            return ['path' => $path, 'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'filename' => 'questions.xlsx'];
        }

        $path = $tmp . '.csv';
        (new Csv($spreadsheet))->save($path);
        $this->auditLogService->log($userId, 'EXPORT', 'Question', null, null, ['format' => 'csv'], null);
        return ['path' => $path, 'mime' => 'text/csv', 'filename' => 'questions.csv'];
    }
}
