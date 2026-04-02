<?php

namespace App\Tests\Unit;

use App\Service\AnalyticsService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class KPICalculationTest extends KernelTestCase
{
    public function testKpisReturnNumericValues(): void
    {
        self::bootKernel();
        $service = static::getContainer()->get(AnalyticsService::class);
        $from = (new \DateTimeImmutable('-60 days'))->format('Y-m-d');
        $to = (new \DateTimeImmutable('+1 day'))->format('Y-m-d');
        $kpis = $service->getComplianceKPIs($from, $to);

        self::assertIsNumeric($kpis['credential_review_volume']);
        self::assertIsNumeric($kpis['approval_rate']);
        self::assertIsNumeric($kpis['rejection_rate']);
        self::assertIsNumeric($kpis['avg_review_turnaround_hours']);
        self::assertIsNumeric($kpis['appointment_utilization_rate']);
        self::assertIsNumeric($kpis['question_bank_growth']);
        self::assertIsNumeric($kpis['question_publish_rate']);
        self::assertIsArray($kpis['active_practitioners_per_firm']);
    }
}
