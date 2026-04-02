<?php

namespace App\Service;

use App\Entity\CredentialSubmission;
use App\Entity\CredentialVersion;
use App\Entity\Practitioner;
use App\Entity\User;
use App\Enum\CredentialState;
use App\Repository\CredentialSubmissionRepository;
use App\Repository\CredentialVersionRepository;
use App\Repository\PractitionerRepository;
use Doctrine\ORM\EntityManagerInterface;

class CredentialWorkflowService
{
    public function __construct(
        private readonly CredentialSubmissionRepository $submissionRepository,
        private readonly CredentialVersionRepository $versionRepository,
        private readonly PractitionerRepository $practitionerRepository,
        private readonly StepUpAuthService $stepUpAuthService,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogService $auditLogService,
        private readonly AlertService $alertService
    ) {
    }

    public function createSubmission(int $practitionerId, User $user, array $payload = []): CredentialSubmission
    {
        $practitioner = $this->practitionerRepository->find($practitionerId);
        if (!$practitioner) {
            throw new ApiException('Practitioner not found', 404);
        }

        $now = new \DateTimeImmutable();
        $submission = (new CredentialSubmission())
            ->setPractitioner($practitioner)
            ->setCurrentState(CredentialState::DRAFT)
            ->setCreatedBy($user)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->entityManager->persist($submission);
        $this->createVersion($submission, CredentialState::DRAFT, $user, $payload, null, $now);
        $this->entityManager->flush();

        return $submission;
    }

    public function submit(CredentialSubmission $submission, User $user, array $payload = []): CredentialSubmission
    {
        $current = $submission->getCurrentState();
        if (!in_array($current, [CredentialState::DRAFT, CredentialState::RESUBMISSION_REQUESTED, CredentialState::REJECTED], true)) {
            $this->invalidTransition($current, CredentialState::SUBMITTED);
        }
        $this->assertSubmissionOwner($submission, $user);

        return $this->transition($submission, CredentialState::SUBMITTED, $user, $payload, null);
    }

    public function startReview(CredentialSubmission $submission, User $user): CredentialSubmission
    {
        $this->assertReviewer($user);
        if ($submission->getCurrentState() !== CredentialState::SUBMITTED) {
            $this->invalidTransition($submission->getCurrentState(), CredentialState::UNDER_REVIEW);
        }

        return $this->transition($submission, CredentialState::UNDER_REVIEW, $user);
    }

    public function approve(CredentialSubmission $submission, User $user): CredentialSubmission
    {
        $this->assertReviewer($user);
        if ($submission->getCurrentState() !== CredentialState::UNDER_REVIEW) {
            $this->invalidTransition($submission->getCurrentState(), CredentialState::APPROVED);
        }

        return $this->transition($submission, CredentialState::APPROVED, $user);
    }

    public function reject(CredentialSubmission $submission, User $user, string $comment): CredentialSubmission
    {
        $this->assertReviewer($user);
        if ($submission->getCurrentState() !== CredentialState::UNDER_REVIEW) {
            $this->invalidTransition($submission->getCurrentState(), CredentialState::REJECTED);
        }

        if (trim($comment) === '') {
            throw new ApiException('Rejection comment is required', 400);
        }

        $result = $this->transition($submission, CredentialState::REJECTED, $user, [], $comment);
        $this->alertService->checkRejectedCredentialsForFirm((int) $submission->getPractitioner()->getFirm()->getId());
        return $result;
    }

    public function requestResubmission(CredentialSubmission $submission, User $user): CredentialSubmission
    {
        $this->assertReviewer($user);
        if ($submission->getCurrentState() !== CredentialState::UNDER_REVIEW) {
            $this->invalidTransition($submission->getCurrentState(), CredentialState::RESUBMISSION_REQUESTED);
        }

        return $this->transition($submission, CredentialState::RESUBMISSION_REQUESTED, $user);
    }

    public function rollback(CredentialSubmission $submission, int $targetVersionNo, User $user, string $password, string $justification): CredentialSubmission
    {
        if ($user->getRole()->value !== 'ROLE_SYSTEM_ADMIN') {
            throw new ApiException('Only system admin can rollback', 403);
        }

        if (!$this->stepUpAuthService->verifyStepUp($user, $password, $justification)) {
            throw new ApiException('Step-up verification failed', 401);
        }

        $target = $this->versionRepository->findOneByVersionNo($submission, $targetVersionNo);
        if (!$target) {
            throw new ApiException('Target version not found', 404);
        }

        $rollbackPayload = [
            'action' => 'ROLLBACK',
            'target_version_no' => $target->getVersionNo(),
            'justification' => $justification,
        ];

        return $this->transition(
            $submission,
            $target->getState(),
            $user,
            $rollbackPayload,
            sprintf('Rollback to version %d: %s', $target->getVersionNo(), $justification)
        );
    }

    /** @return CredentialSubmission[] */
    public function queue(?CredentialState $state, User $user): array
    {
        if (in_array($user->getRole()->value, ['ROLE_CREDENTIAL_REVIEWER', 'ROLE_SYSTEM_ADMIN'], true)) {
            return $this->submissionRepository->findQueue($state);
        }

        return $this->submissionRepository->findQueueByOwner($state, (int) $user->getId());
    }

    public function assertCanViewSubmission(CredentialSubmission $submission, User $user): void
    {
        if (in_array($user->getRole()->value, ['ROLE_CREDENTIAL_REVIEWER', 'ROLE_SYSTEM_ADMIN'], true)) {
            return;
        }

        if ((int) $submission->getCreatedBy()->getId() !== (int) $user->getId()) {
            throw new ApiException('Forbidden', 403);
        }
    }

    /** @return CredentialVersion[] */
    public function versions(CredentialSubmission $submission, User $user): array
    {
        $this->assertCanViewSubmission($submission, $user);
        return $this->versionRepository->findBySubmission($submission);
    }

    public function getLatestVersion(CredentialSubmission $submission): ?CredentialVersion
    {
        return $this->versionRepository->findLatestForSubmission($submission);
    }

    public function findOrCreateDraftSubmissionForPractitioner(Practitioner $practitioner, User $user): CredentialSubmission
    {
        $submission = $this->submissionRepository->findLatestForPractitioner($practitioner);
        if ($submission) {
            return $submission;
        }

        return $this->createSubmission((int) $practitioner->getId(), $user);
    }

    private function transition(
        CredentialSubmission $submission,
        CredentialState $targetState,
        User $user,
        array $payload = [],
        ?string $comment = null
    ): CredentialSubmission {
        $now = new \DateTimeImmutable();
        $submission->setCurrentState($targetState);
        $submission->setUpdatedAt($now);

        $this->createVersion($submission, $targetState, $user, $payload, $comment, $now);
        $this->entityManager->flush();

        $action = match ($targetState) {
            CredentialState::APPROVED => 'APPROVE',
            CredentialState::REJECTED => 'REJECT',
            default => null,
        };
        if (($payload['action'] ?? null) === 'ROLLBACK') {
            $action = 'ROLLBACK';
        }
        if ($action) {
            $this->auditLogService->log((int) $user->getId(), $action, 'CredentialSubmission', (int) $submission->getId(), null, [
                'state' => $targetState->value,
                'comment' => $comment,
            ], null);
        }

        return $submission;
    }

    private function createVersion(
        CredentialSubmission $submission,
        CredentialState $state,
        User $user,
        array $payload,
        ?string $comment,
        \DateTimeImmutable $createdAt
    ): CredentialVersion {
        $latest = $this->versionRepository->findLatestForSubmission($submission);
        $nextVersion = ($latest?->getVersionNo() ?? 0) + 1;

        $version = (new CredentialVersion())
            ->setSubmission($submission)
            ->setVersionNo($nextVersion)
            ->setPayloadJson(json_encode($payload, JSON_THROW_ON_ERROR))
            ->setState($state)
            ->setRejectionComment($comment)
            ->setCreatedBy($user)
            ->setCreatedAt($createdAt);

        $this->entityManager->persist($version);

        return $version;
    }

    private function invalidTransition(CredentialState $from, CredentialState $to): never
    {
        throw new ApiException(
            sprintf('Invalid transition from %s to %s', $from->value, $to->value),
            422,
            ['current_state' => $from->value, 'target_state' => $to->value]
        );
    }

    private function assertReviewer(User $user): void
    {
        if (!in_array($user->getRole()->value, ['ROLE_CREDENTIAL_REVIEWER', 'ROLE_SYSTEM_ADMIN'], true)) {
            throw new ApiException('Reviewer role required', 403);
        }
    }

    private function assertSubmissionOwner(CredentialSubmission $submission, User $user): void
    {
        if ($user->getRole()->value === 'ROLE_SYSTEM_ADMIN') {
            return;
        }

        if ($submission->getCreatedBy()->getId() !== $user->getId()) {
            throw new ApiException('Only the submission owner can perform this transition', 403);
        }
    }
}
