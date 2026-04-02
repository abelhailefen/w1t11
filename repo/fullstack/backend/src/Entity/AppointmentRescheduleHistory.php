<?php

namespace App\Entity;

use App\Repository\AppointmentRescheduleHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppointmentRescheduleHistoryRepository::class)]
#[ORM\Table(name: 'appointment_reschedule_history')]
class AppointmentRescheduleHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Appointment::class)]
    #[ORM\JoinColumn(name: 'appointment_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Appointment $appointment;

    #[ORM\ManyToOne(targetEntity: AppointmentSlot::class)]
    #[ORM\JoinColumn(name: 'old_slot_id', referencedColumnName: 'id', nullable: false)]
    private AppointmentSlot $oldSlot;

    #[ORM\ManyToOne(targetEntity: AppointmentSlot::class)]
    #[ORM\JoinColumn(name: 'new_slot_id', referencedColumnName: 'id', nullable: false)]
    private AppointmentSlot $newSlot;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'changed_by', referencedColumnName: 'id', nullable: false)]
    private User $changedBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $changedAt;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAppointment(): Appointment
    {
        return $this->appointment;
    }

    public function setAppointment(Appointment $appointment): self
    {
        $this->appointment = $appointment;
        return $this;
    }

    public function getOldSlot(): AppointmentSlot
    {
        return $this->oldSlot;
    }

    public function setOldSlot(AppointmentSlot $oldSlot): self
    {
        $this->oldSlot = $oldSlot;
        return $this;
    }

    public function getNewSlot(): AppointmentSlot
    {
        return $this->newSlot;
    }

    public function setNewSlot(AppointmentSlot $newSlot): self
    {
        $this->newSlot = $newSlot;
        return $this;
    }

    public function getChangedBy(): User
    {
        return $this->changedBy;
    }

    public function setChangedBy(User $changedBy): self
    {
        $this->changedBy = $changedBy;
        return $this;
    }

    public function getChangedAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function setChangedAt(\DateTimeImmutable $changedAt): self
    {
        $this->changedAt = $changedAt;
        return $this;
    }
}
