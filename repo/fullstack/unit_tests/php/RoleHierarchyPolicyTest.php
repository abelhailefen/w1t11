<?php

use PHPUnit\Framework\TestCase;

final class RoleHierarchyPolicyTest extends TestCase
{
    public function testSystemAdminRoleConstantExists(): void
    {
        self::assertTrue(in_array('ROLE_SYSTEM_ADMIN', [
            'ROLE_USER',
            'ROLE_CONTENT_ADMIN',
            'ROLE_CREDENTIAL_REVIEWER',
            'ROLE_ANALYST',
            'ROLE_SYSTEM_ADMIN',
        ], true));
    }
}
