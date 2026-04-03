<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\AppointmentSlot;
use App\Entity\CredentialSubmission;
use App\Entity\CredentialVersion;
use App\Entity\Practitioner;
use App\Entity\Question;
use App\Entity\QuestionVersion;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AnalyticsService
{
    private const ALLOWED_FIELDS = [
        'practitioners' => [
            'id' => 'id',
            'full_name' => 'fullName',
            'firm_id' => 'firm',
            'license_jurisdiction' => 'licenseJurisdiction',
            'status' => 'status',
            'created_at' => 'createdAt',
        ],
        'credentials' => [
            'id' => 'id',
            'practitioner_id' => 'practitioner',
            'current_state' => 'currentState',
            'created_at' => 'createdAt',
            'updated_at' => 'updatedAt',
        ],
        'appointments' => [
            'id' => 'id',
            'practitioner_id' => 'practitioner',
            'location_id' => 'location',
            'state' => 'state',
            'booked_at' => 'bookedAt',
            'cancelled_at' => 'cancelledAt',
        ],
        'questions' => [
            'id' => 'id',
            'category_id' => 'category',
            'status' => 'status',
            'created_at' => 'createdAt',
            'updated_at' => 'updatedAt',
        ],
    ];

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function executeQuery(array $definition): array
    {
        $entityType = (string) ($definition['entity_type'] ?? '');
        $aggregation = (string) ($definition['aggregation'] ?? 'count');
        $groupBy = (string) ($definition['group_by'] ?? '');
        $filters = is_array($definition['filters'] ?? null) ? $definition['filters'] : [];
        $page = max(1, (int) ($definition['page'] ?? 1));
        $limit = min(200, max(1, (int) ($definition['limit'] ?? 50)));

        [$entityClass, $alias, $dateField] = match ($entityType) {
            'practitioners' => [Practitioner::class, 'e', 'createdAt'],
            'credentials' => [CredentialSubmission::class, 'e', 'createdAt'],
            'appointments' => [Appointment::class, 'e', 'bookedAt'],
            'questions' => [Question::class, 'e', 'createdAt'],
            default => throw new ApiException('Invalid entity_type', 400),
        };

        $qb = $this->entityManager->createQueryBuilder()->from($entityClass, $alias);
        if ($aggregation === 'count') {
            $qb->select('COUNT(' . $alias . '.id) as aggregate_value');
        } elseif ($aggregation === 'avg') {
            $field = (string) ($definition['aggregation_field'] ?? 'id');
            $resolvedField = $this->resolveAllowedField($entityType, $field);
            $qb->select('AVG(' . $this->toFieldExpression($alias, $resolvedField) . ') as aggregate_value');
        } elseif ($aggregation === 'sum') {
            $field = (string) ($definition['aggregation_field'] ?? 'id');
            $resolvedField = $this->resolveAllowedField($entityType, $field);
            $qb->select('SUM(' . $this->toFieldExpression($alias, $resolvedField) . ') as aggregate_value');
        } else {
            throw new ApiException('Invalid aggregation', 400);
        }

        if ($groupBy !== '') {
            $resolvedGroupBy = $this->resolveAllowedField($entityType, $groupBy);
            $groupByExpression = $this->toFieldExpression($alias, $resolvedGroupBy);
            $qb->addSelect($groupByExpression . ' as group_value')->groupBy($groupByExpression);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere($alias . '.status = :status')->setParameter('status', $filters['status']);
        }
        if (!empty($filters['date_from'])) {
            $qb->andWhere($alias . '.' . $dateField . ' >= :dateFrom')->setParameter('dateFrom', new \DateTimeImmutable((string) $filters['date_from']));
        }
        if (!empty($filters['date_to'])) {
            $qb->andWhere($alias . '.' . $dateField . ' <= :dateTo')->setParameter('dateTo', new \DateTimeImmutable((string) $filters['date_to']));
        }

        $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit);
        return ['items' => $qb->getQuery()->getArrayResult(), 'pagination' => ['page' => $page, 'limit' => $limit]];
    }

    private function resolveAllowedField(string $entityType, string $field): string
    {
        $field = trim($field);
        $allowed = self::ALLOWED_FIELDS[$entityType] ?? null;
        if ($allowed === null || !isset($allowed[$field])) {
            $allowedFields = $allowed ? array_keys($allowed) : [];
            throw new ApiException(sprintf("Invalid field: '%s'. Allowed fields: [%s]", $field, implode(', ', $allowedFields)), 400);
        }

        return $allowed[$field];
    }

    private function toFieldExpression(string $alias, string $field): string
    {
        return in_array($field, ['firm', 'practitioner', 'location', 'category'], true)
            ? sprintf('IDENTITY(%s.%s)', $alias, $field)
            : sprintf('%s.%s', $alias, $field);
    }

    public function getComplianceKPIs(string $dateFrom, string $dateTo, ?int $orgUnitId = null): array
    {
        $from = new \DateTimeImmutable($dateFrom . ' 00:00:00');
        $to = new \DateTimeImmutable($dateTo . ' 23:59:59');

        $volume = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(cs.id)')->from(CredentialSubmission::class, 'cs')
            ->andWhere('cs.createdAt >= :from AND cs.createdAt <= :to')
            ->andWhere($this->orgScopeExistsSubquery('cs.practitioner', $orgUnitId))
            ->setParameter('from', $from)->setParameter('to', $to)
            ->getQuery()->getSingleScalarResult();

        $reviewed = (int) $this->entityManager->createQueryBuilder()->select('COUNT(cs.id)')->from(CredentialSubmission::class, 'cs')
            ->andWhere('cs.updatedAt >= :from AND cs.updatedAt <= :to')
            ->andWhere('cs.currentState IN (:states)')
            ->andWhere($this->orgScopeExistsSubquery('cs.practitioner', $orgUnitId))
            ->setParameter('from', $from)->setParameter('to', $to)
            ->setParameter('states', ['APPROVED', 'REJECTED'])
            ->getQuery()->getSingleScalarResult();
        $approved = (int) $this->entityManager->createQueryBuilder()->select('COUNT(cs.id)')->from(CredentialSubmission::class, 'cs')
            ->andWhere('cs.updatedAt >= :from AND cs.updatedAt <= :to')
            ->andWhere('cs.currentState = :state')->setParameter('state', 'APPROVED')
            ->andWhere($this->orgScopeExistsSubquery('cs.practitioner', $orgUnitId))
            ->setParameter('from', $from)->setParameter('to', $to)
            ->getQuery()->getSingleScalarResult();
        $rejected = (int) $this->entityManager->createQueryBuilder()->select('COUNT(cs.id)')->from(CredentialSubmission::class, 'cs')
            ->andWhere('cs.updatedAt >= :from AND cs.updatedAt <= :to')
            ->andWhere('cs.currentState = :state')->setParameter('state', 'REJECTED')
            ->andWhere($this->orgScopeExistsSubquery('cs.practitioner', $orgUnitId))
            ->setParameter('from', $from)->setParameter('to', $to)
            ->getQuery()->getSingleScalarResult();

        $turnRows = $this->entityManager->createQueryBuilder()
            ->select('IDENTITY(cv.submission) as submission_id, cv.state as state, cv.createdAt as created_at')
            ->from(CredentialVersion::class, 'cv')
            ->join('cv.submission', 'cs')
            ->andWhere('cv.createdAt >= :from AND cv.createdAt <= :to')
            ->andWhere('cv.state IN (:states)')
            ->andWhere($this->orgScopeExistsSubquery('cs.practitioner', $orgUnitId))
            ->setParameter('from', $from)->setParameter('to', $to)
            ->setParameter('states', ['SUBMITTED', 'APPROVED'])
            ->orderBy('cv.createdAt', 'ASC')
            ->getQuery()->getArrayResult();
        $perSubmission = [];
        foreach ($turnRows as $r) {
            $sid = (int) $r['submission_id'];
            if (!isset($perSubmission[$sid])) {
                $perSubmission[$sid] = ['submitted' => null, 'approved' => null];
            }
            if ($r['state'] === 'SUBMITTED' && $perSubmission[$sid]['submitted'] === null) {
                $perSubmission[$sid]['submitted'] = $r['created_at'];
            }
            if ($r['state'] === 'APPROVED') {
                $perSubmission[$sid]['approved'] = $r['created_at'];
            }
        }
        $hours = [];
        foreach ($perSubmission as $entry) {
            if ($entry['submitted'] && $entry['approved']) {
                $s = $entry['submitted'] instanceof \DateTimeInterface ? $entry['submitted'] : new \DateTimeImmutable((string) $entry['submitted']);
                $a = $entry['approved'] instanceof \DateTimeInterface ? $entry['approved'] : new \DateTimeImmutable((string) $entry['approved']);
                $hours[] = max(0.0, ($a->getTimestamp() - $s->getTimestamp()) / 3600);
            }
        }
        $avgTurn = count($hours) > 0 ? array_sum($hours) / count($hours) : 0.0;

        $totalSlots = (int) $this->entityManager->createQueryBuilder()->select('COUNT(s.id)')->from(AppointmentSlot::class, 's')
            ->join('s.practitioner', 'p')
            ->andWhere('s.startAt >= :from AND s.startAt <= :to')
            ->andWhere($this->orgScopeExistsSubquery('p', $orgUnitId))
            ->setParameter('from', $from)->setParameter('to', $to)->getQuery()->getSingleScalarResult();
        $bookedSlots = (int) $this->entityManager->createQueryBuilder()->select('COUNT(a.id)')->from(Appointment::class, 'a')
            ->andWhere('a.state = :state')->setParameter('state', 'CONFIRMED')
            ->andWhere('a.bookedAt >= :from AND a.bookedAt <= :to')
            ->andWhere($this->orgScopeExistsSubquery('a.practitioner', $orgUnitId))
            ->setParameter('from', $from)->setParameter('to', $to)->getQuery()->getSingleScalarResult();

        $questionCreated = (int) $this->entityManager->createQueryBuilder()->select('COUNT(q.id)')->from(Question::class, 'q')
            ->andWhere('q.createdAt >= :from AND q.createdAt <= :to')
            ->setParameter('from', $from)->setParameter('to', $to)->getQuery()->getSingleScalarResult();
        $questionPublished = (int) $this->entityManager->createQueryBuilder()->select('COUNT(q.id)')->from(Question::class, 'q')
            ->andWhere('q.createdAt >= :from AND q.createdAt <= :to')->andWhere('q.status = :status')->setParameter('status', 'PUBLISHED')
            ->setParameter('from', $from)->setParameter('to', $to)->getQuery()->getSingleScalarResult();

        $firmRows = $this->entityManager->createQueryBuilder()
            ->select('f.name as firm_name, COUNT(p.id) as practitioner_count')
            ->from(Practitioner::class, 'p')->join('p.firm', 'f')
            ->andWhere('p.status = :status')->setParameter('status', 'ACTIVE')
            ->andWhere($this->orgScopeConditionForFirmAlias('f', $orgUnitId))
            ->groupBy('f.id')->getQuery()->getArrayResult();

        $resubmissionRequested = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT cs.id)')
            ->from(CredentialSubmission::class, 'cs')
            ->join(CredentialVersion::class, 'cv', 'WITH', 'cv.submission = cs')
            ->andWhere('cv.state = :state')
            ->andWhere('cv.createdAt >= :from AND cv.createdAt <= :to')
            ->andWhere($this->orgScopeExistsSubquery('cs.practitioner', $orgUnitId))
            ->setParameter('state', 'RESUBMISSION_REQUESTED')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()->getSingleScalarResult();

        $resubmissionApproved = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(DISTINCT cs.id)')
            ->from(CredentialSubmission::class, 'cs')
            ->join(CredentialVersion::class, 'cvRequest', 'WITH', 'cvRequest.submission = cs')
            ->andWhere('cvRequest.state = :requested')
            ->andWhere('cs.currentState = :approved')
            ->andWhere($this->orgScopeExistsSubquery('cs.practitioner', $orgUnitId))
            ->setParameter('requested', 'RESUBMISSION_REQUESTED')
            ->setParameter('approved', 'APPROVED')
            ->getQuery()->getSingleScalarResult();

        $newUsers = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(u.id)')
            ->from(User::class, 'u')
            ->andWhere('u.createdAt >= :from AND u.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()->getSingleScalarResult();

        $newPractitioners = (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Practitioner::class, 'p')
            ->andWhere('p.createdAt >= :from AND p.createdAt <= :to')
            ->andWhere($this->orgScopeExistsSubquery('p', $orgUnitId))
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()->getSingleScalarResult();

        $firmDistribution = [];
        foreach ($firmRows as $row) {
            $firmDistribution[(string) $row['firm_name']] = (int) $row['practitioner_count'];
        }

        $resubmissionSuccessRate = $resubmissionRequested > 0 ? round(($resubmissionApproved / $resubmissionRequested) * 100, 2) : 0.0;
        $onboardingRate = $newUsers > 0 ? round(($newPractitioners / $newUsers) * 100, 2) : 0.0;
        $questionBankRefreshRate = $questionCreated > 0 ? round(($questionPublished / $questionCreated) * 100, 2) : 0.0;

        return [
            'rescue_volume' => $volume,
            'recovery_rate' => $resubmissionSuccessRate,
            'adoption_conversion' => $onboardingRate,
            'average_shelter_stay' => round($avgTurn, 2),
            'donation_mix' => $firmDistribution,
            'supply_turnover' => $questionBankRefreshRate,
            'credential_review_volume' => $volume,
            'approval_rate' => $reviewed > 0 ? round(($approved / $reviewed) * 100, 2) : 0.0,
            'rejection_rate' => $reviewed > 0 ? round(($rejected / $reviewed) * 100, 2) : 0.0,
            'avg_review_turnaround_hours' => round($avgTurn, 2),
            'appointment_utilization_rate' => $totalSlots > 0 ? round(($bookedSlots / $totalSlots) * 100, 2) : 0.0,
            'resubmission_success_rate' => $resubmissionSuccessRate,
            'new_practitioner_onboarding_rate' => $onboardingRate,
            'firm_distribution' => $firmDistribution,
            'question_bank_refresh_rate' => $questionBankRefreshRate,
            'question_bank_growth' => $questionCreated,
            'question_publish_rate' => $questionBankRefreshRate,
            'active_practitioners_per_firm' => $firmRows,
        ];
    }

    public function getTrendData(string $metric, string $dateFrom, string $dateTo, string $interval): array
    {
        $from = new \DateTimeImmutable($dateFrom);
        $to = new \DateTimeImmutable($dateTo);
        $points = [];
        $cursor = $from;
        while ($cursor <= $to) {
            $next = match ($interval) {
                'weekly' => $cursor->modify('+1 week'),
                'monthly' => $cursor->modify('+1 month'),
                default => $cursor->modify('+1 day'),
            };
            $value = $this->countMetricInRange($metric, $cursor, $next->modify('-1 second'));
            $points[] = ['time' => $cursor->format('Y-m-d'), 'value' => $value];
            $cursor = $next;
        }

        return ['metric' => $metric, 'interval' => $interval, 'points' => $points];
    }

    public function getDistributionData(string $metric, string $dateFrom, string $dateTo): array
    {
        $from = new \DateTimeImmutable($dateFrom . ' 00:00:00');
        $to = new \DateTimeImmutable($dateTo . ' 23:59:59');
        if ($metric === 'credentials_by_state') {
            $rows = $this->entityManager->createQueryBuilder()->select('cs.currentState as label, COUNT(cs.id) as value')->from(CredentialSubmission::class, 'cs')
                ->andWhere('cs.createdAt >= :from AND cs.createdAt <= :to')->setParameter('from', $from)->setParameter('to', $to)
                ->groupBy('cs.currentState')->getQuery()->getArrayResult();
            return ['items' => $rows];
        }
        if ($metric === 'questions_by_difficulty') {
            $rows = $this->entityManager->createQueryBuilder()->select('qv.difficulty as label, COUNT(qv.id) as value')->from(QuestionVersion::class, 'qv')
                ->andWhere('qv.createdAt >= :from AND qv.createdAt <= :to')->setParameter('from', $from)->setParameter('to', $to)
                ->groupBy('qv.difficulty')->getQuery()->getArrayResult();
            return ['items' => $rows];
        }

        throw new ApiException('Unsupported distribution metric', 400);
    }

    public function getCorrelationData(string $metricX, string $metricY, string $dateFrom, string $dateTo): array
    {
        $trendX = $this->getTrendData($metricX, $dateFrom, $dateTo, 'daily')['points'];
        $trendY = $this->getTrendData($metricY, $dateFrom, $dateTo, 'daily')['points'];
        $points = [];
        $max = min(count($trendX), count($trendY));
        for ($i = 0; $i < $max; $i++) {
            $points[] = ['x' => $trendX[$i]['value'], 'y' => $trendY[$i]['value'], 'time' => $trendX[$i]['time']];
        }
        return ['metricX' => $metricX, 'metricY' => $metricY, 'points' => $points];
    }

    private function countMetricInRange(string $metric, \DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        return match ($metric) {
            'credential_submissions' => (int) $this->entityManager->createQueryBuilder()->select('COUNT(cs.id)')->from(CredentialSubmission::class, 'cs')
                ->andWhere('cs.createdAt >= :from AND cs.createdAt <= :to')->setParameter('from', $from)->setParameter('to', $to)
                ->getQuery()->getSingleScalarResult(),
            'appointments_confirmed' => (int) $this->entityManager->createQueryBuilder()->select('COUNT(a.id)')->from(Appointment::class, 'a')
                ->andWhere('a.state = :state')->setParameter('state', 'CONFIRMED')
                ->andWhere('a.bookedAt >= :from AND a.bookedAt <= :to')->setParameter('from', $from)->setParameter('to', $to)
                ->getQuery()->getSingleScalarResult(),
            'questions_created' => (int) $this->entityManager->createQueryBuilder()->select('COUNT(q.id)')->from(Question::class, 'q')
                ->andWhere('q.createdAt >= :from AND q.createdAt <= :to')->setParameter('from', $from)->setParameter('to', $to)
                ->getQuery()->getSingleScalarResult(),
            default => throw new ApiException('Unsupported trend metric', 400),
        };
    }

    private function orgScopeExistsSubquery(string $practitionerExpression, ?int $orgUnitId): string
    {
        if ($orgUnitId === null) {
            return '1 = 1';
        }

        return sprintf(
            'EXISTS (SELECT 1 FROM %s p_org JOIN p_org.firm f_org WHERE p_org = %s AND f_org.orgUnit = %d)',
            Practitioner::class,
            $practitionerExpression,
            $orgUnitId
        );
    }

    private function orgScopeConditionForFirmAlias(string $firmAlias, ?int $orgUnitId): string
    {
        if ($orgUnitId === null) {
            return '1 = 1';
        }

        return sprintf('%s.orgUnit = %d', $firmAlias, $orgUnitId);
    }
}
