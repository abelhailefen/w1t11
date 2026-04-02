<?php

use PHPUnit\Framework\TestCase;

final class SmokeUnitTest extends TestCase
{
    public function testUnitPipelineIsWired(): void
    {
        self::assertSame(2, 1 + 1);
    }
}
