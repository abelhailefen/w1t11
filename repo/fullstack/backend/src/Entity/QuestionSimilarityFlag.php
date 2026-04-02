<?php

namespace App\Entity;

use App\Enum\QuestionSimilarityStatus;
use App\Repository\QuestionSimilarityFlagRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionSimilarityFlagRepository::class)]
#[ORM\Table(name: 'question_similarity_flags')]
class QuestionSimilarityFlag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: QuestionVersion::class)]
    #[ORM\JoinColumn(name: 'question_version_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private QuestionVersion $questionVersion;

    #[ORM\ManyToOne(targetEntity: Question::class)]
    #[ORM\JoinColumn(name: 'matched_question_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Question $matchedQuestion;

    #[ORM\Column(type: 'float')]
    private float $similarityScore;

    #[ORM\Column(type: 'float')]
    private float $threshold;

    #[ORM\Column(type: 'string', length: 20, enumType: QuestionSimilarityStatus::class)]
    private QuestionSimilarityStatus $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
}
