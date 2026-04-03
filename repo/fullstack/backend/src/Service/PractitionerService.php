<?php

namespace App\Service;

use App\Entity\CredentialFile;
use App\Entity\Firm;
use App\Entity\Practitioner;
use App\Entity\SensitiveAccessLog;
use App\Entity\User;
use App\Enum\PractitionerStatus;
use App\Repository\CredentialFileRepository;
use App\Repository\CredentialVersionRepository;
use App\Repository\FirmRepository;
use App\Repository\PractitionerRepository;
use Doctrine\ORM\EntityManagerInterface;

class PractitionerService
{
    public function __construct(
        private readonly PractitionerRepository $practitionerRepository,
        private readonly FirmRepository $firmRepository,
        private readonly CredentialFileRepository $credentialFileRepository,
        private readonly CredentialVersionRepository $credentialVersionRepository,
        private readonly EncryptionService $encryptionService,
        private readonly FileUploadService $fileUploadService,
        private readonly CredentialWorkflowService $credentialWorkflowService,
        private readonly EntityManagerInterface $entityManager,
        private readonly AuditLogService $auditLogService,
        private readonly AlertService $alertService
    ) {
    }

    public function list(int $page, int $limit, ?string $name, ?string $status, ?int $firmId): array
    {
        $qb = $this->practitionerRepository->createQueryBuilder('p')
            ->join('p.firm', 'f')
            ->orderBy('p.updatedAt', 'DESC');

        if ($name) {
            $qb->andWhere('LOWER(p.fullName) LIKE :name')->setParameter('name', '%' . mb_strtolower($name) . '%');
        }
        if ($status) {
            try {
                $statusEnum = PractitionerStatus::from($status);
                $qb->andWhere('p.status = :status')->setParameter('status', $statusEnum);
            } catch (\ValueError) {
                $qb->andWhere('1 = 0');
            }
        }
        if ($firmId) {
            $qb->andWhere('f.id = :firmId')->setParameter('firmId', $firmId);
        }

        $total = (int) (clone $qb)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $items = $qb->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery()->getResult();

        return [
            'items' => array_map(fn (Practitioner $p) => $this->toMaskedArray($p), $items),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
            ],
        ];
    }

    public function create(array $payload, User $actor): Practitioner
    {
        $firm = $this->findFirmOrFail((int) $payload['firm_id']);
        $now = new \DateTimeImmutable();

        $practitioner = (new Practitioner())
            ->setFirm($firm)
            ->setFullName($payload['full_name'])
            ->setLicenseNumberEncrypted($this->encryptionService->encrypt($payload['license_number']))
            ->setLicenseJurisdiction($payload['license_jurisdiction'])
            ->setContactEmail($payload['contact_email'] ?? null)
            ->setContactPhone($payload['contact_phone'] ?? null)
            ->setStatus(isset($payload['status']) ? PractitionerStatus::from($payload['status']) : PractitionerStatus::ACTIVE)
            ->setCreatedBy($actor)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);

        $this->entityManager->persist($practitioner);
        $this->entityManager->flush();

        return $practitioner;
    }

    public function update(Practitioner $practitioner, array $payload): Practitioner
    {
        if (isset($payload['firm_id'])) {
            $practitioner->setFirm($this->findFirmOrFail((int) $payload['firm_id']));
        }
        if (isset($payload['full_name'])) {
            $practitioner->setFullName($payload['full_name']);
        }
        if (isset($payload['license_number'])) {
            $practitioner->setLicenseNumberEncrypted($this->encryptionService->encrypt($payload['license_number']));
        }
        if (isset($payload['license_jurisdiction'])) {
            $practitioner->setLicenseJurisdiction($payload['license_jurisdiction']);
        }
        if (array_key_exists('contact_email', $payload)) {
            $practitioner->setContactEmail($payload['contact_email']);
        }
        if (array_key_exists('contact_phone', $payload)) {
            $practitioner->setContactPhone($payload['contact_phone']);
        }
        if (isset($payload['status'])) {
            $practitioner->setStatus(PractitionerStatus::from($payload['status']));
        }

        $practitioner->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $practitioner;
    }

    public function revealLicense(Practitioner $practitioner, User $user, string $reason, ?string $ipAddress): array
    {
        if (!in_array($user->getRole()->value, ['ROLE_CREDENTIAL_REVIEWER', 'ROLE_SYSTEM_ADMIN'], true)) {
            throw new ApiException('Forbidden', 403);
        }

        $license = $this->resolveStoredLicense($practitioner);
        $log = (new SensitiveAccessLog())
            ->setUser($user)
            ->setEntityType('practitioner')
            ->setEntityId((int) $practitioner->getId())
            ->setFieldName('license_number')
            ->setReason($reason)
            ->setIpAddress($ipAddress)
            ->setAccessedAt(new \DateTimeImmutable());
        $this->entityManager->persist($log);
        $this->entityManager->flush();
        $this->auditLogService->log((int) $user->getId(), 'LICENSE_REVEAL', 'Practitioner', (int) $practitioner->getId(), null, ['reason' => $reason], $ipAddress);
        $this->alertService->checkLicenseRevealForUser((int) $user->getId());

        return [
            'license_number' => $license,
            'masked_license_number' => $this->encryptionService->mask($license),
        ];
    }

    public function uploadCredentialFile(Practitioner $practitioner, \Symfony\Component\HttpFoundation\File\UploadedFile $file, User $user): CredentialFile
    {
        $result = $this->fileUploadService->storeCredentialFile($file, (int) $practitioner->getId());
        $submission = $this->credentialWorkflowService->findOrCreateDraftSubmissionForPractitioner($practitioner, $user);
        $version = $this->credentialVersionRepository->findLatestForSubmission($submission);
        if (!$version) {
            throw new ApiException('No credential version found for submission', 500);
        }

        $entity = (new CredentialFile())
            ->setCredentialVersion($version)
            ->setOriginalName($result['original_name'])
            ->setMimeType($result['mime_type'])
            ->setSizeBytes((int) $result['size_bytes'])
            ->setStoragePath($result['storage_path'])
            ->setUploadedAt(new \DateTimeImmutable());

        $this->entityManager->persist($entity);
        $this->entityManager->flush();

        return $entity;
    }

    /** @return CredentialFile[] */
    public function listCredentialFiles(Practitioner $practitioner): array
    {
        return $this->credentialFileRepository->findByPractitioner($practitioner);
    }

    public function toMaskedArray(Practitioner $practitioner): array
    {
        $license = $this->resolveStoredLicense($practitioner);

        return [
            'id' => $practitioner->getId(),
            'full_name' => $practitioner->getFullName(),
            'firm' => [
                'id' => $practitioner->getFirm()->getId(),
                'name' => $practitioner->getFirm()->getName(),
            ],
            'license_number' => $this->encryptionService->mask($license),
            'license_jurisdiction' => $practitioner->getLicenseJurisdiction(),
            'contact_email' => $practitioner->getContactEmail(),
            'contact_phone' => $practitioner->getContactPhone(),
            'status' => $practitioner->getStatus()->value,
            'created_at' => $practitioner->getCreatedAt()->format(DATE_ATOM),
            'updated_at' => $practitioner->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    private function findFirmOrFail(int $id): Firm
    {
        $firm = $this->firmRepository->find($id);
        if (!$firm) {
            throw new ApiException('Firm not found', 404);
        }
        return $firm;
    }

    private function resolveStoredLicense(Practitioner $practitioner): string
    {
        $stored = $practitioner->getLicenseNumberEncrypted();

        try {
            return $this->encryptionService->decrypt($stored);
        } catch (\RuntimeException) {
            return $stored;
        }
    }
}
