<?php

namespace App\Tests\Unit;

use App\Service\AlertService;
use App\Service\AuditLogService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AuditFailureVisibilityTest extends TestCase
{
    public function testAuditFailureIsLoggedAndRaisesCriticalAlert(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('insert')->willThrowException(new \RuntimeException('flush failed'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                self::stringContains('AUDIT LOG FAILURE'),
                self::arrayHasKey('action_type')
            );

        $alertService = $this->createMock(AlertService::class);
        $alertService->expects(self::once())
            ->method('createCriticalAlert')
            ->with(
                'AUDIT_LOG_FAILURE',
                self::stringContains('Audit log write failed'),
                self::arrayHasKey('action_type')
            );

        $service = new AuditLogService($connection, $logger, $alertService);
        $service->log(1, 'UPDATE', 'Practitioner', 10, null, ['foo' => 'bar'], '127.0.0.1');
        self::assertTrue(true);
    }
}
