<?php

namespace App\Tests\Unit;

use App\Entity\User;
use App\Enum\CredentialState;
use App\Repository\CredentialSubmissionRepository;
use App\Service\ApiException;
use App\Service\CredentialWorkflowService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CredentialStateMachineTest extends KernelTestCase
{
    private CredentialWorkflowService $workflowService;
    private CredentialSubmissionRepository $submissionRepository;
    private User $user;
    private User $reviewer;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->workflowService = $container->get(CredentialWorkflowService::class);
        $this->submissionRepository = $container->get(CredentialSubmissionRepository::class);

        /** @var User $user */
        $user = $container->get('doctrine')->getRepository(User::class)->findOneBy(['username' => 'user']);
        /** @var User $reviewer */
        $reviewer = $container->get('doctrine')->getRepository(User::class)->findOneBy(['username' => 'reviewer']);
        $this->user = $user;
        $this->reviewer = $reviewer;
    }

    public function testValidTransitions(): void
    {
        $submission = $this->workflowService->createSubmission(1, $this->user, ['origin' => 'unit']);
        $submission = $this->workflowService->submit($submission, $this->user, ['step' => 'submit']);
        self::assertSame(CredentialState::SUBMITTED, $submission->getCurrentState());

        $submission = $this->workflowService->startReview($submission, $this->reviewer);
        self::assertSame(CredentialState::UNDER_REVIEW, $submission->getCurrentState());

        $submission = $this->workflowService->requestResubmission($submission, $this->reviewer);
        self::assertSame(CredentialState::RESUBMISSION_REQUESTED, $submission->getCurrentState());

        $submission = $this->workflowService->submit($submission, $this->user, ['resubmit' => true]);
        self::assertSame(CredentialState::SUBMITTED, $submission->getCurrentState());
    }

    public function testInvalidTransitionsAndRejectionCommentRule(): void
    {
        $submission = $this->workflowService->createSubmission(1, $this->user);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Invalid transition from DRAFT to APPROVED');
        $this->workflowService->approve($submission, $this->reviewer);
    }

    public function testRejectWithoutCommentFails(): void
    {
        $submission = $this->workflowService->createSubmission(1, $this->user);
        $submission = $this->workflowService->submit($submission, $this->user);
        $submission = $this->workflowService->startReview($submission, $this->reviewer);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Rejection comment is required');
        $this->workflowService->reject($submission, $this->reviewer, '   ');
    }
}
