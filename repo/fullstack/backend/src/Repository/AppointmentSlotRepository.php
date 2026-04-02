<?php

namespace App\Repository;

use App\Entity\AppointmentSlot;
use App\Enum\AppointmentSlotStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AppointmentSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppointmentSlot::class);
    }

    /** @return AppointmentSlot[] */
    public function findAvailableByRange(int $practitionerId, string $from, string $to): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.practitioner = :pid')
            ->andWhere('s.startAt >= :from')->andWhere('s.endAt <= :to')
            ->andWhere('s.status = :status')
            ->andWhere('s.availableCount > 0')
            ->setParameter('pid', $practitionerId)
            ->setParameter('from', new \DateTimeImmutable($from))
            ->setParameter('to', new \DateTimeImmutable($to))
            ->setParameter('status', AppointmentSlotStatus::AVAILABLE)
            ->orderBy('s.startAt', 'ASC')
            ->getQuery()->getResult();
    }
}
