<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 64)]
    private string $actionType;

    #[ORM\Column(type: 'string', length: 64, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $entityId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $oldValueJson = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $newValueJson = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $retentionExpiresAt = null;

    public function getId(): ?int { return $this->id; }
    public function getOccurredAt(): \DateTimeImmutable { return $this->occurredAt; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getActionType(): string { return $this->actionType; }
    public function setActionType(string $actionType): self { $this->actionType = $actionType; return $this; }
    public function getEntityType(): ?string { return $this->entityType; }
    public function setEntityType(?string $entityType): self { $this->entityType = $entityType; return $this; }
    public function getEntityId(): ?int { return $this->entityId; }
    public function setEntityId(?int $entityId): self { $this->entityId = $entityId; return $this; }
    public function getOldValueJson(): ?string { return $this->oldValueJson; }
    public function setOldValueJson(?string $oldValueJson): self { $this->oldValueJson = $oldValueJson; return $this; }
    public function getNewValueJson(): ?string { return $this->newValueJson; }
    public function setNewValueJson(?string $newValueJson): self { $this->newValueJson = $newValueJson; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ipAddress): self { $this->ipAddress = $ipAddress; return $this; }
    public function getRetentionExpiresAt(): ?\DateTimeImmutable { return $this->retentionExpiresAt; }
    public function setRetentionExpiresAt(?\DateTimeImmutable $retentionExpiresAt): self { $this->retentionExpiresAt = $retentionExpiresAt; return $this; }
}
