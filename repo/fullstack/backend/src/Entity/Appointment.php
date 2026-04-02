<?php

namespace App\Entity;

use App\Enum\AppointmentState;
use App\Repository\AppointmentRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
#[ORM\Table(name: 'appointments')]
class Appointment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Practitioner::class)]
    #[ORM\JoinColumn(name: 'practitioner_id', referencedColumnName: 'id', nullable: false)]
    private Practitioner $practitioner;

    #[ORM\ManyToOne(targetEntity: Location::class)]
    #[ORM\JoinColumn(name: 'location_id', referencedColumnName: 'id', nullable: false)]
    private Location $location;

    #[ORM\ManyToOne(targetEntity: AppointmentSlot::class)]
    #[ORM\JoinColumn(name: 'slot_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppointmentSlot $slot = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'booked_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $bookedBy = null;

    #[ORM\Column(type: 'string', length: 32, enumType: AppointmentState::class)]
    private AppointmentState $state = AppointmentState::HELD;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $heldUntil = null;

    #[ORM\Column(type: 'integer')]
    private int $rescheduleCount = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $bookedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

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

    public function getSlot(): ?AppointmentSlot
    {
        return $this->slot;
    }

    public function setSlot(?AppointmentSlot $slot): self
    {
        $this->slot = $slot;
        return $this;
    }

    public function getBookedBy(): ?User
    {
        return $this->bookedBy;
    }

    public function setBookedBy(?User $bookedBy): self
    {
        $this->bookedBy = $bookedBy;
        return $this;
    }

    public function getState(): AppointmentState
    {
        return $this->state;
    }

    public function setState(AppointmentState $state): self
    {
        $this->state = $state;
        return $this;
    }

    public function getHeldUntil(): ?\DateTimeImmutable
    {
        return $this->heldUntil;
    }

    public function setHeldUntil(?\DateTimeImmutable $heldUntil): self
    {
        $this->heldUntil = $heldUntil;
        return $this;
    }

    public function getRescheduleCount(): int
    {
        return $this->rescheduleCount;
    }

    public function setRescheduleCount(int $rescheduleCount): self
    {
        $this->rescheduleCount = $rescheduleCount;
        return $this;
    }

    public function getBookedAt(): ?\DateTimeImmutable
    {
        return $this->bookedAt;
    }

    public function setBookedAt(?\DateTimeImmutable $bookedAt): self
    {
        $this->bookedAt = $bookedAt;
        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): self
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }
}
