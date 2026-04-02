<?php

namespace App\Entity;

use App\Enum\QuestionImportStatus;
use App\Repository\QuestionImportJobRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionImportJobRepository::class)]
#[ORM\Table(name: 'question_import_jobs')]
class QuestionImportJob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'initiated_by', referencedColumnName: 'id', nullable: false)]
    private User $initiatedBy;

    #[ORM\Column(type: 'string', length: 255)]
    private string $fileName;

    #[ORM\Column(type: 'string', length: 16)]
    private string $format;

    #[ORM\Column(type: 'string', length: 20, enumType: QuestionImportStatus::class)]
    private QuestionImportStatus $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $resultJson = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;
}
