<?php

namespace App\Entity;

use App\Enum\AppointmentSlotStatus;
use App\Repository\AppointmentSlotRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentSlotRepository::class)]
#[ORM\Table(name: 'appointment_slots')]
class AppointmentSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Practitioner::class)]
    #[ORM\JoinColumn(name: 'practitioner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Practitioner $practitioner;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(name: 'location_id', referencedColumnName: 'id', nullable: false)]
    private Location $location;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $endAt;

    #[ORM\Column(type: 'integer')]
    private int $capacity = 1;

    #[ORM\Column(type: 'integer')]
    private int $availableCount = 1;

    #[ORM\Column(type: 'string', length: 32, enumType: AppointmentSlotStatus::class)]
    private AppointmentSlotStatus $status = AppointmentSlotStatus::AVAILABLE;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPractitioner(): Practitioner
    {
        return $this->practitioner;
    }

    public function setPractitioner(Practitioner $practitioner): self
    {
        $this->practitioner = $practitioner;
        return $this;
    }

    public function getLocation(): Location
    {
        return $this->location;
    }

    public function setLocation(Location $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getStartAt(): \DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): self
    {
        $this->startAt = $startAt;
        return $this;
    }

    public function getEndAt(): \DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): self
    {
        $this->endAt = $endAt;
        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getAvailableCount(): int
    {
        return $this->availableCount;
    }

    public function setAvailableCount(int $availableCount): self
    {
        $this->availableCount = $availableCount;
        return $this;
    }

    public function getStatus(): AppointmentSlotStatus
    {
        return $this->status;
    }

    public function setStatus(AppointmentSlotStatus $status): self
    {
        $this->status = $status;
        return $this;
    }
}
