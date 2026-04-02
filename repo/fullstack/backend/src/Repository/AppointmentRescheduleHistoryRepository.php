<?php

namespace App\Repository;

use App\Entity\AppointmentRescheduleHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AppointmentRescheduleHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppointmentRescheduleHistory::class);
    }
}
