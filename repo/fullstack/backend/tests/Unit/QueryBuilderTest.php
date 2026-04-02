<?php

namespace App\Tests\Unit;

use App\Service\AnalyticsService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class QueryBuilderTest extends KernelTestCase
{
    public function testPractitionerStatusFilterQueryRuns(): void
    {
        self::bootKernel();
        $service = static::getContainer()->get(AnalyticsService::class);
        $result = $service->executeQuery([
            'entity_type' => 'practitioners',
            'filters' => ['status' => 'ACTIVE'],
            'aggregation' => 'count',
            'group_by' => '',
        ]);

        self::assertArrayHasKey('items', $result);
        self::assertNotEmpty($result['items']);
    }
}
