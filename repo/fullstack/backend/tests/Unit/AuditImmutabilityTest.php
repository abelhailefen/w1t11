<?php

namespace App\Tests\Unit;

use App\Entity\AuditLog;
use PHPUnit\Framework\TestCase;

class AuditImmutabilityTest extends TestCase
{
    public function testAuditLogHasNoMutableIdOrOccurredAtSetters(): void
    {
        self::assertFalse(method_exists(AuditLog::class, 'setId'));
        self::assertFalse(method_exists(AuditLog::class, 'setOccurredAt'));
    }
}
