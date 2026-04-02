<?php

namespace App\Repository;

use App\Entity\AnalyticsSavedQuery;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AnalyticsSavedQueryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AnalyticsSavedQuery::class);
    }
}
