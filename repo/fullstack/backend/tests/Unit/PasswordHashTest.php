<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PasswordHashTest extends TestCase
{
    public function testBcryptHashUsesCostAtLeastTwelve(): void
    {
        $hash = password_hash('Secret@123', PASSWORD_BCRYPT, ['cost' => 12]);
        $info = password_get_info($hash);

        self::assertSame('bcrypt', $info['algoName']);
        self::assertGreaterThanOrEqual(12, $info['options']['cost']);
    }
}
