<?php

namespace App\Entity;

use App\Enum\CredentialState;
use App\Repository\CredentialVersionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CredentialVersionRepository::class)]
#[ORM\Table(name: 'credential_versions')]
class CredentialVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CredentialSubmission::class, inversedBy: 'versions')]
    #[ORM\JoinColumn(name: 'submission_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private CredentialSubmission $submission;

    #[ORM\Column(type: 'integer')]
    private int $versionNo;

    #[ORM\Column(type: 'text')]
    private string $payloadJson = '{}';

    #[ORM\Column(type: 'string', length: 40, enumType: CredentialState::class)]
    private CredentialState $state;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionComment = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, CredentialFile> */
    #[ORM\OneToMany(mappedBy: 'credentialVersion', targetEntity: CredentialFile::class, cascade: ['remove'])]
    private Collection $files;

    public function __construct()
    {
        $this->files = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubmission(): CredentialSubmission
    {
        return $this->submission;
    }

    public function setSubmission(CredentialSubmission $submission): self
    {
        $this->submission = $submission;
        return $this;
    }

    public function getVersionNo(): int
    {
        return $this->versionNo;
    }

    public function setVersionNo(int $versionNo): self
    {
        $this->versionNo = $versionNo;
        return $this;
    }

    public function getPayloadJson(): string
    {
        return $this->payloadJson;
    }

    public function setPayloadJson(string $payloadJson): self
    {
        $this->payloadJson = $payloadJson;
        return $this;
    }

    public function getState(): CredentialState
    {
        return $this->state;
    }

    public function setState(CredentialState $state): self
    {
        $this->state = $state;
        return $this;
    }

    public function getRejectionComment(): ?string
    {
        return $this->rejectionComment;
    }

    public function setRejectionComment(?string $rejectionComment): self
    {
        $this->rejectionComment = $rejectionComment;
        return $this;
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /** @return Collection<int, CredentialFile> */
    public function getFiles(): Collection
    {
        return $this->files;
    }
}
