<?php

namespace App\Entity;

use App\Repository\AlertRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
#[ORM\Table(name: 'alerts')]
class Alert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $alertType;

    #[ORM\Column(type: 'string', length: 16)]
    private string $severity;

    #[ORM\Column(type: 'string', length: 500)]
    private string $message;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contextJson = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $triggeredAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'acknowledged_by', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $acknowledgedBy = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    public function getId(): ?int { return $this->id; }
    public function getAlertType(): string { return $this->alertType; }
    public function setAlertType(string $alertType): self { $this->alertType = $alertType; return $this; }
    public function getSeverity(): string { return $this->severity; }
    public function setSeverity(string $severity): self { $this->severity = $severity; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): self { $this->message = $message; return $this; }
    public function getContextJson(): ?string { return $this->contextJson; }
    public function setContextJson(?string $contextJson): self { $this->contextJson = $contextJson; return $this; }
    public function getTriggeredAt(): \DateTimeImmutable { return $this->triggeredAt; }
    public function setTriggeredAt(\DateTimeImmutable $triggeredAt): self { $this->triggeredAt = $triggeredAt; return $this; }
    public function getAcknowledgedBy(): ?User { return $this->acknowledgedBy; }
    public function setAcknowledgedBy(?User $acknowledgedBy): self { $this->acknowledgedBy = $acknowledgedBy; return $this; }
    public function getAcknowledgedAt(): ?\DateTimeImmutable { return $this->acknowledgedAt; }
    public function setAcknowledgedAt(?\DateTimeImmutable $acknowledgedAt): self { $this->acknowledgedAt = $acknowledgedAt; return $this; }
}
