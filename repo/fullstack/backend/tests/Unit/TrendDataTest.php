<?php

namespace App\Tests\Unit;

use App\Service\AnalyticsService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TrendDataTest extends KernelTestCase
{
    public function testDailyTrendReturnsSevenPoints(): void
    {
        self::bootKernel();
        $service = static::getContainer()->get(AnalyticsService::class);
        $from = (new \DateTimeImmutable('-6 days'))->format('Y-m-d');
        $to = (new \DateTimeImmutable())->format('Y-m-d');
        $trend = $service->getTrendData('credential_submissions', $from, $to, 'daily');
        self::assertCount(7, $trend['points']);
    }
}
