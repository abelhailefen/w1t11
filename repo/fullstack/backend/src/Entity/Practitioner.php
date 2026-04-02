<?php

namespace App\Entity;

use App\Enum\PractitionerStatus;
use App\Repository\PractitionerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PractitionerRepository::class)]
#[ORM\Table(name: 'practitioners')]
class Practitioner
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Firm::class)]
    #[ORM\JoinColumn(name: 'firm_id', referencedColumnName: 'id', nullable: false)]
    private Firm $firm;

    #[ORM\Column(type: 'string', length: 255)]
    private string $fullName;

    #[ORM\Column(type: 'text')]
    private string $licenseNumberEncrypted;

    #[ORM\Column(type: 'string', length: 120)]
    private string $licenseJurisdiction;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $contactEmail = null;

    #[ORM\Column(type: 'string', length: 80, nullable: true)]
    private ?string $contactPhone = null;

    #[ORM\Column(type: 'string', length: 32, enumType: PractitionerStatus::class)]
    private PractitionerStatus $status = PractitionerStatus::ACTIVE;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, CredentialFile> */
    #[ORM\OneToMany(mappedBy: 'practitioner', targetEntity: CredentialFile::class, cascade: ['remove'])]
    private Collection $credentialFiles;

    public function __construct()
    {
        $this->credentialFiles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirm(): Firm
    {
        return $this->firm;
    }

    public function setFirm(Firm $firm): self
    {
        $this->firm = $firm;
        return $this;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): self
    {
        $this->fullName = $fullName;
        return $this;
    }

    public function getLicenseNumberEncrypted(): string
    {
        return $this->licenseNumberEncrypted;
    }

    public function setLicenseNumberEncrypted(string $licenseNumberEncrypted): self
    {
        $this->licenseNumberEncrypted = $licenseNumberEncrypted;
        return $this;
    }

    public function getLicenseJurisdiction(): string
    {
        return $this->licenseJurisdiction;
    }

    public function setLicenseJurisdiction(string $licenseJurisdiction): self
    {
        $this->licenseJurisdiction = $licenseJurisdiction;
        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): self
    {
        $this->contactEmail = $contactEmail;
        return $this;
    }

    public function getContactPhone(): ?string
    {
        return $this->contactPhone;
    }

    public function setContactPhone(?string $contactPhone): self
    {
        $this->contactPhone = $contactPhone;
        return $this;
    }

    public function getStatus(): PractitionerStatus
    {
        return $this->status;
    }

    public function setStatus(PractitionerStatus $status): self
    {
        $this->status = $status;
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

    /** @return Collection<int, CredentialFile> */
    public function getCredentialFiles(): Collection
    {
        return $this->credentialFiles;
    }
}
