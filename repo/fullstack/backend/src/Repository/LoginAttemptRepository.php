<?php

namespace App\Repository;

use App\Entity\LoginAttempt;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LoginAttemptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LoginAttempt::class);
    }

    /**
     * @return LoginAttempt[]
     */
    public function findRecentByUsername(string $username, \DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('la')
            ->andWhere('la.username = :username')
            ->andWhere('la.attemptedAt >= :since')
            ->setParameter('username', $username)
            ->setParameter('since', $since)
            ->orderBy('la.attemptedAt', 'DESC')
            ->addOrderBy('la.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
