<?php

namespace App\Repository;

use App\Entity\Location;
use App\Enum\LocationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class LocationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Location::class);
    }

    /** @return Location[] */
    public function findActive(): array
    {
        return $this->findBy(['status' => LocationStatus::ACTIVE], ['name' => 'ASC']);
    }
}
