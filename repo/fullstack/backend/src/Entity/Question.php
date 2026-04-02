<?php

namespace App\Entity;

use App\Enum\QuestionStatus;
use App\Repository\QuestionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
#[ORM\Table(name: 'questions')]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: QuestionCategory::class)]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false)]
    private QuestionCategory $category;

    #[ORM\Column(type: 'string', length: 32, enumType: QuestionStatus::class)]
    private QuestionStatus $status = QuestionStatus::DRAFT;

    #[ORM\Column(name: 'current_version_id', type: 'integer', nullable: true)]
    private ?int $currentVersionId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: 'boolean')]
    private bool $duplicateAcknowledged = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function getId(): ?int { return $this->id; }
    public function getCategory(): QuestionCategory { return $this->category; }
    public function setCategory(QuestionCategory $category): self { $this->category = $category; return $this; }
    public function getStatus(): QuestionStatus { return $this->status; }
    public function setStatus(QuestionStatus $status): self { $this->status = $status; return $this; }
    public function getCurrentVersionId(): ?int { return $this->currentVersionId; }
    public function setCurrentVersionId(?int $currentVersionId): self { $this->currentVersionId = $currentVersionId; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $createdBy): self { $this->createdBy = $createdBy; return $this; }
    public function isDuplicateAcknowledged(): bool { return $this->duplicateAcknowledged; }
    public function setDuplicateAcknowledged(bool $duplicateAcknowledged): self { $this->duplicateAcknowledged = $duplicateAcknowledged; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
}
