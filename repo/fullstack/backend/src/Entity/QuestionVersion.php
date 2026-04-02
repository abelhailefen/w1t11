<?php

namespace App\Entity;

use App\Repository\QuestionVersionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionVersionRepository::class)]
#[ORM\Table(name: 'question_versions')]
class QuestionVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Question::class)]
    #[ORM\JoinColumn(name: 'question_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Question $question;

    #[ORM\Column(type: 'integer')]
    private int $versionNo;

    #[ORM\Column(type: 'text')]
    private string $contentHtml;

    #[ORM\Column(type: 'text')]
    private string $plainTextIndex;

    #[ORM\Column(type: 'integer')]
    private int $difficulty;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $metadataJson = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function getId(): ?int { return $this->id; }
    public function getQuestion(): Question { return $this->question; }
    public function setQuestion(Question $question): self { $this->question = $question; return $this; }
    public function getVersionNo(): int { return $this->versionNo; }
    public function setVersionNo(int $versionNo): self { $this->versionNo = $versionNo; return $this; }
    public function getContentHtml(): string { return $this->contentHtml; }
    public function setContentHtml(string $contentHtml): self { $this->contentHtml = $contentHtml; return $this; }
    public function getPlainTextIndex(): string { return $this->plainTextIndex; }
    public function setPlainTextIndex(string $plainTextIndex): self { $this->plainTextIndex = $plainTextIndex; return $this; }
    public function getDifficulty(): int { return $this->difficulty; }
    public function setDifficulty(int $difficulty): self { $this->difficulty = $difficulty; return $this; }
    public function getMetadataJson(): ?string { return $this->metadataJson; }
    public function setMetadataJson(?string $metadataJson): self { $this->metadataJson = $metadataJson; return $this; }
    public function getCreatedBy(): User { return $this->createdBy; }
    public function setCreatedBy(User $createdBy): self { $this->createdBy = $createdBy; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
}
