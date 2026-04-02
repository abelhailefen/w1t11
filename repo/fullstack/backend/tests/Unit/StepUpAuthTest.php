<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Service\StepUpAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class StepUpAuthTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    public function testStepUpVerification(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $container->get(EntityManagerInterface::class);
        /** @var StepUpAuthService $stepUp */
        $stepUp = $container->get(StepUpAuthService::class);
        /** @var User $user */
        $user = $entityManager->getRepository(User::class)->findOneBy(['username' => 'admin']);

        self::assertTrue($stepUp->verifyStepUp($user, 'Admin@123', 'Rollback approved by compliance lead'));
        self::assertFalse($stepUp->verifyStepUp($user, 'WrongPassword!', 'Rollback approved by compliance lead'));
    }
}
