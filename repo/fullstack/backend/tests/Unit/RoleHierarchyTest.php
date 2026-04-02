<?php

namespace App\Tests\Unit;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

class RoleHierarchyTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testSystemAdminInheritsAllRoles(): void
    {
        self::bootKernel();

        /** @var RoleHierarchyInterface $hierarchy */
        $hierarchy = static::getContainer()->get(RoleHierarchyInterface::class);
        $reachable = $hierarchy->getReachableRoleNames(['ROLE_SYSTEM_ADMIN']);

        self::assertContains('ROLE_USER', $reachable);
        self::assertContains('ROLE_CONTENT_ADMIN', $reachable);
        self::assertContains('ROLE_CREDENTIAL_REVIEWER', $reachable);
        self::assertContains('ROLE_ANALYST', $reachable);
    }
}
