<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AnalyticsService;
use App\Service\ApiException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/dashboards')]
class DashboardController extends ApiController
{
    public function __construct(private readonly AnalyticsService $analyticsService)
    {
    }

    #[Route('/compliance', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/dashboards/compliance', tags: ['Analytics'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Compliance KPIs')])]
    public function compliance(Request $request): JsonResponse
    {
        $user = $this->requireAnalyst();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        $from = (string) $request->query->get('from', (new \DateTimeImmutable('-30 days'))->format('Y-m-d'));
        $to = (string) $request->query->get('to', (new \DateTimeImmutable())->format('Y-m-d'));
        $orgUnit = $request->query->getInt('org_unit') ?: null;

        return $this->ok($this->analyticsService->getComplianceKPIs($from, $to, $orgUnit));
    }

    #[Route('/trend', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/dashboards/trend', tags: ['Analytics'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Trend data')])]
    public function trend(Request $request): JsonResponse
    {
        $user = $this->requireAnalyst();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        try {
            return $this->ok($this->analyticsService->getTrendData(
                (string) $request->query->get('metric', 'credential_submissions'),
                (string) $request->query->get('from', (new \DateTimeImmutable('-7 days'))->format('Y-m-d')),
                (string) $request->query->get('to', (new \DateTimeImmutable())->format('Y-m-d')),
                (string) $request->query->get('interval', 'daily')
            ));
        } catch (ApiException $e) {
            return $this->fromApiException($e);
        }
    }

    #[Route('/distribution', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/dashboards/distribution', tags: ['Analytics'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Distribution data')])]
    public function distribution(Request $request): JsonResponse
    {
        $user = $this->requireAnalyst();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        try {
            return $this->ok($this->analyticsService->getDistributionData(
                (string) $request->query->get('metric', 'credentials_by_state'),
                (string) $request->query->get('from', (new \DateTimeImmutable('-30 days'))->format('Y-m-d')),
                (string) $request->query->get('to', (new \DateTimeImmutable())->format('Y-m-d'))
            ));
        } catch (ApiException $e) {
            return $this->fromApiException($e);
        }
    }

    #[Route('/correlation', methods: ['GET'])]
    #[OA\Get(path: '/api/v1/dashboards/correlation', tags: ['Analytics'], security: [['Bearer' => []]], responses: [new OA\Response(response: 200, description: 'Correlation data')])]
    public function correlation(Request $request): JsonResponse
    {
        $user = $this->requireAnalyst();
        if ($user instanceof JsonResponse) {
            return $user;
        }
        try {
            return $this->ok($this->analyticsService->getCorrelationData(
                (string) $request->query->get('metricX', 'credential_submissions'),
                (string) $request->query->get('metricY', 'questions_created'),
                (string) $request->query->get('from', (new \DateTimeImmutable('-30 days'))->format('Y-m-d')),
                (string) $request->query->get('to', (new \DateTimeImmutable())->format('Y-m-d')),
            ));
        } catch (ApiException $e) {
            return $this->fromApiException($e);
        }
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
