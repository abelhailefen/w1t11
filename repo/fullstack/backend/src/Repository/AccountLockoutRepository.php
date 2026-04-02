<?php

namespace App\Repository;

use App\Entity\AccountLockout;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AccountLockoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AccountLockout::class);
    }

    public function findOneByUser(User $user): ?AccountLockout
    {
        return $this->findOneBy(['user' => $user]);
    }
}
