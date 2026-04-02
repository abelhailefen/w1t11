<?php

use PHPUnit\Framework\TestCase;

final class PasswordHashPolicyTest extends TestCase
{
    public function testHashCostPolicy(): void
    {
        $hash = password_hash('Policy@123', PASSWORD_BCRYPT, ['cost' => 12]);
        $info = password_get_info($hash);
        self::assertGreaterThanOrEqual(12, $info['options']['cost']);
    }
}
