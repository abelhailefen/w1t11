<?php

namespace App\Entity;

use App\Enum\CredentialState;
use App\Repository\CredentialSubmissionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CredentialSubmissionRepository::class)]
#[ORM\Table(name: 'credential_submissions')]
class CredentialSubmission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Practitioner::class)]
    #[ORM\JoinColumn(name: 'practitioner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Practitioner $practitioner;

    #[ORM\Column(type: 'string', length: 40, enumType: CredentialState::class)]
    private CredentialState $currentState = CredentialState::DRAFT;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by_id', referencedColumnName: 'id', nullable: false)]
    private User $createdBy;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, CredentialVersion> */
    #[ORM\OneToMany(mappedBy: 'submission', targetEntity: CredentialVersion::class, cascade: ['remove'])]
    private Collection $versions;

    public function __construct()
    {
        $this->versions = new ArrayCollection();
    }

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

    public function getCurrentState(): CredentialState
    {
        return $this->currentState;
    }

    public function setCurrentState(CredentialState $currentState): self
    {
        $this->currentState = $currentState;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /** @return Collection<int, CredentialVersion> */
    public function getVersions(): Collection
    {
        return $this->versions;
    }
}
