<?php

namespace App\Repository;

use App\Entity\CredentialSubmission;
use App\Entity\Practitioner;
use App\Enum\CredentialState;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CredentialSubmissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CredentialSubmission::class);
    }

    public function findLatestForPractitioner(Practitioner $practitioner): ?CredentialSubmission
    {
        return $this->findOneBy(['practitioner' => $practitioner], ['id' => 'DESC']);
    }

    /** @return CredentialSubmission[] */
    public function findQueue(?CredentialState $state): array
    {
        $qb = $this->createQueryBuilder('s')
            ->join('s.practitioner', 'p')
            ->join('p.firm', 'f')
            ->orderBy('s.updatedAt', 'DESC');

        if ($state) {
            $qb->andWhere('s.currentState = :state')->setParameter('state', $state);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return CredentialSubmission[] */
    public function findQueueByOwner(?CredentialState $state, int $ownerId): array
    {
        $qb = $this->createQueryBuilder('s')
            ->join('s.practitioner', 'p')
            ->join('p.firm', 'f')
            ->andWhere('IDENTITY(s.createdBy) = :ownerId')
            ->setParameter('ownerId', $ownerId)
            ->orderBy('s.updatedAt', 'DESC');

        if ($state) {
            $qb->andWhere('s.currentState = :state')->setParameter('state', $state);
        }

        return $qb->getQuery()->getResult();
    }
}
