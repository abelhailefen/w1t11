<?php

namespace App\Repository;

use App\Entity\CredentialFile;
use App\Entity\CredentialVersion;
use App\Entity\Practitioner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CredentialFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CredentialFile::class);
    }

    /** @return CredentialFile[] */
    public function findByPractitioner(Practitioner $practitioner): array
    {
        return $this->createQueryBuilder('f')
            ->join('f.credentialVersion', 'v')
            ->join('v.submission', 's')
            ->where('s.practitioner = :practitioner')
            ->setParameter('practitioner', $practitioner)
            ->orderBy('f.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return CredentialFile[] */
    public function findByVersion(CredentialVersion $version): array
    {
        return $this->findBy(['credentialVersion' => $version], ['uploadedAt' => 'DESC']);
    }

    public function findOneForPractitioner(Practitioner $practitioner, int $fileId): ?CredentialFile
    {
        return $this->createQueryBuilder('f')
            ->join('f.credentialVersion', 'v')
            ->join('v.submission', 's')
            ->where('f.id = :fileId')
            ->andWhere('s.practitioner = :practitioner')
            ->setParameter('fileId', $fileId)
            ->setParameter('practitioner', $practitioner)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
