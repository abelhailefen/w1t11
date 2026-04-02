<?php

namespace App\Entity;

use App\Repository\CredentialFileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CredentialFileRepository::class)]
#[ORM\Table(name: 'credential_files')]
class CredentialFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Practitioner::class, inversedBy: 'credentialFiles')]
    #[ORM\JoinColumn(name: 'practitioner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Practitioner $practitioner;

    #[ORM\Column(type: 'string', length: 255)]
    private string $originalName;

    #[ORM\Column(type: 'string', length: 120)]
    private string $mimeType;

    #[ORM\Column(type: 'bigint')]
    private int $sizeBytes;

    #[ORM\Column(type: 'string', length: 1024)]
    private string $storagePath;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $uploadedAt;

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

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): self
    {
        $this->originalName = $originalName;
        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getSizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function setSizeBytes(int $sizeBytes): self
    {
        $this->sizeBytes = $sizeBytes;
        return $this;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function setStoragePath(string $storagePath): self
    {
        $this->storagePath = $storagePath;
        return $this;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeImmutable $uploadedAt): self
    {
        $this->uploadedAt = $uploadedAt;
        return $this;
    }
}
