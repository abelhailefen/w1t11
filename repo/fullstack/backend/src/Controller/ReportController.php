<?php

namespace App\Controller;

use App\Entity\AnalyticsSavedQuery;
use App\Entity\User;
use App\Repository\AnalyticsSavedQueryRepository;
use App\Service\AnalyticsService;
use App\Service\ApiException;
use App\Service\ReportExportService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/reports')]
class ReportController extends ApiController
{
    public function __construct(
        private readonly AnalyticsSavedQueryRepository $savedQueryRepository,
        private readonly AnalyticsService $analyticsService,
        private readonly ReportExportService $reportExportService
    ) {
    }

    #[Route('/export.csv', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/reports/export.csv', tags: ['Analytics'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'CSV export')])]
    public function exportCsv(Request $request): BinaryFileResponse|JsonResponse
    {
        $user = $this->requireAnalyst();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $queryId = (int) $request->query->get('query_id', 0);
        /** @var AnalyticsSavedQuery|null $saved */
        $saved = $this->savedQueryRepository->find($queryId);
        if (!$saved) {
            return $this->error('Saved query not found', 404);
        }
        try {
            $result = $this->analyticsService->executeQuery(json_decode($saved->getQueryDefinitionJson(), true, 512, JSON_THROW_ON_ERROR));
            $file = $this->reportExportService->exportToCsv($result['items']);
        } catch (\Throwable $e) {
            return $this->fromApiException($e instanceof ApiException ? $e : new ApiException($e->getMessage(), 500));
        }

        $response = new BinaryFileResponse($file['path']);
        $response->headers->set('Content-Type', $file['mime']);
        $response->setContentDisposition('attachment', $file['filename']);
        return $response;
    }

    #[Route('/export.pdf', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/reports/export.pdf', tags: ['Analytics'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'PDF export')])]
    public function exportPdf(Request $request): BinaryFileResponse|JsonResponse
    {
        $user = $this->requireAnalyst();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        try {
            $data = $this->analyticsService->getComplianceKPIs(
                (string) $request->query->get('from', (new \DateTimeImmutable('-30 days'))->format('Y-m-d')),
                (string) $request->query->get('to', (new \DateTimeImmutable())->format('Y-m-d')),
                $request->query->getInt('org_unit') ?: null
            );
            $file = $this->reportExportService->exportToPdf($data);
        } catch (\Throwable $e) {
            return $this->fromApiException($e instanceof ApiException ? $e : new ApiException($e->getMessage(), 500));
        }

        $response = new BinaryFileResponse($file['path']);
        $response->headers->set('Content-Type', $file['mime']);
        $response->setContentDisposition('attachment', $file['filename']);
        return $response;
    }

    private function requireAnalyst(): User|JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->error('Unauthorized', 401);
        }
        if (!in_array($user->getRole()->value, ['ROLE_ANALYST', 'ROLE_SYSTEM_ADMIN'], true)) {
            return $this->error('Analyst role required', 403);
        }

        return $user;
    }
}
