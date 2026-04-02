<?php

namespace App\Repository;

use App\Entity\CredentialSubmission;
use App\Entity\CredentialVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CredentialVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CredentialVersion::class);
    }

    public function findLatestForSubmission(CredentialSubmission $submission): ?CredentialVersion
    {
        return $this->findOneBy(['submission' => $submission], ['versionNo' => 'DESC']);
    }

    /** @return CredentialVersion[] */
    public function findBySubmission(CredentialSubmission $submission): array
    {
        return $this->findBy(['submission' => $submission], ['versionNo' => 'ASC']);
    }

    public function findOneByVersionNo(CredentialSubmission $submission, int $versionNo): ?CredentialVersion
    {
        return $this->findOneBy(['submission' => $submission, 'versionNo' => $versionNo]);
    }
}
