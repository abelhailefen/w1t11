<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Repository\CredentialVersionRepository;
use App\Service\ApiException;
use App\Service\CredentialWorkflowService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RollbackTest extends KernelTestCase
{
    private CredentialWorkflowService $workflowService;
    private CredentialVersionRepository $versionRepository;
    private User $user;
    private User $admin;
    private User $reviewer;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->workflowService = $container->get(CredentialWorkflowService::class);
        $this->versionRepository = $container->get(CredentialVersionRepository::class);

        $userRepo = $container->get('doctrine')->getRepository(User::class);
        $this->user = $userRepo->findOneBy(['username' => 'user']);
        $this->admin = $userRepo->findOneBy(['username' => 'admin']);
        $this->reviewer = $userRepo->findOneBy(['username' => 'reviewer']);
    }

    public function testRollbackCreatesNewVersion(): void
    {
        $submission = $this->workflowService->createSubmission(1, $this->user);
        $submission = $this->workflowService->submit($submission, $this->user);
        $submission = $this->workflowService->startReview($submission, $this->reviewer);
        $submission = $this->workflowService->approve($submission, $this->reviewer);

        $before = count($this->versionRepository->findBySubmission($submission));
        $rolledBack = $this->workflowService->rollback($submission, 2, $this->admin, 'Admin@123', 'undo accidental approval');
        $after = count($this->versionRepository->findBySubmission($submission));

        self::assertSame('SUBMITTED', $rolledBack->getCurrentState()->value);
        self::assertSame($before + 1, $after);
    }

    public function testRollbackByNonAdminFails(): void
    {
        $submission = $this->workflowService->createSubmission(1, $this->user);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Only system admin can rollback');
        $this->workflowService->rollback($submission, 1, $this->user, 'User@123', 'nope');
    }

    public function testRollbackWithoutValidStepUpFails(): void
    {
        $submission = $this->workflowService->createSubmission(1, $this->user);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Step-up verification failed');
        $this->workflowService->rollback($submission, 1, $this->admin, 'wrong-password', 'justify rollback');
    }
}
