<?php

namespace App\Repository;

use App\Entity\CredentialFile;
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
        return $this->findBy(['practitioner' => $practitioner], ['uploadedAt' => 'DESC']);
    }
}
