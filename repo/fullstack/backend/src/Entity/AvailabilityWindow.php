<?php

namespace App\Entity;

use App\Repository\AvailabilityWindowRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AvailabilityWindowRepository::class)]
#[ORM\Table(name: 'availability_windows')]
class AvailabilityWindow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Practitioner::class)]
    #[ORM\JoinColumn(name: 'practitioner_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Practitioner $practitioner = null;

    #[ORM\Column(name: 'org_unit_id', type: 'integer', nullable: true)]
    private ?int $orgUnitId = null;

    #[ORM\Column(type: 'integer')]
    private int $weekday;

    #[ORM\Column(type: 'time_immutable')]
    private \DateTimeImmutable $startTime;

    #[ORM\Column(type: 'time_immutable')]
    private \DateTimeImmutable $endTime;

    #[ORM\Column(type: 'integer')]
    private int $slotMinutes = 30;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPractitioner(): ?Practitioner
    {
        return $this->practitioner;
    }

    public function setPractitioner(?Practitioner $practitioner): self
    {
        $this->practitioner = $practitioner;
        return $this;
    }

    public function getOrgUnitId(): ?int
    {
        return $this->orgUnitId;
    }

    public function setOrgUnitId(?int $orgUnitId): self
    {
        $this->orgUnitId = $orgUnitId;
        return $this;
    }

    public function getWeekday(): int
    {
        return $this->weekday;
    }

    public function setWeekday(int $weekday): self
    {
        $this->weekday = $weekday;
        return $this;
    }

    public function getStartTime(): \DateTimeImmutable
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeImmutable $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): \DateTimeImmutable
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeImmutable $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getSlotMinutes(): int
    {
        return $this->slotMinutes;
    }

    public function setSlotMinutes(int $slotMinutes): self
    {
        $this->slotMinutes = $slotMinutes;
        return $this;
    }
}
