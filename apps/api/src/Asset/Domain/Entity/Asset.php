<?php

declare(strict_types=1);

namespace App\Asset\Domain\Entity;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use LogicException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stored binary file (image, PDF, video, …) with tenant scope.
 *
 * Per ADR-009 Asset is split between two homes: this dedicated table
 * carries storage details (path on object storage, mime type, size,
 * EXIF / dimensions JSONB), while user-defined metadata flows through
 * the catalog model — every Asset row optionally points at a
 * `CatalogObject` of `kind=asset` (`object_id` UNIQUE) so admins can
 * attach localised attributes (`alt_pl`, `caption_en`, …) the same way
 * they do for products. Tightly-coupled storage details stay here so
 * we are not forcing them through the EAV layer.
 *
 * `storage_path` is relative to the Flysystem bucket (e.g. `<tenant>/
 * <asset-id>/original.jpg`) — application-layer convention,
 * Flysystem-agnostic.
 *
 * Variants (thumbnails, transcodes, alternative formats) live in
 * {@see AssetVariant} and are derived in phase 1 (transformations are
 * out of MVP scope — #37 only handles the original upload).
 */
class Asset implements TenantScoped
{
    private Uuid $id;

    private ?Tenant $tenant = null;

    #[Assert\NotBlank]
    #[Assert\Length(max: 128)]
    private string $code;

    private ?CatalogObject $object = null;

    #[Assert\NotBlank]
    private string $originalFilename;

    private string $mimeType;

    private int $size;

    /**
     * @var array<string, mixed>
     */
    private array $metadata = [];

    #[Assert\NotBlank]
    private string $storagePath;

    private DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, AssetVariant>
     */
    private Collection $variants;

    public function __construct(
        string $code,
        string $originalFilename,
        string $mimeType,
        int $size,
        string $storagePath,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->code = $code;
        $this->originalFilename = $originalFilename;
        $this->mimeType = $mimeType;
        $this->size = $size;
        $this->storagePath = $storagePath;
        $this->createdAt = new DateTimeImmutable();
        $this->variants = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * @internal stamped by TenantAssignmentListener on prePersist
     */
    public function assignTenant(Tenant $tenant): void
    {
        if (null !== $this->tenant) {
            throw new LogicException('Tenant is already assigned and cannot be reassigned.');
        }

        $this->tenant = $tenant;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getObject(): ?CatalogObject
    {
        return $this->object;
    }

    public function linkToObject(?CatalogObject $object): void
    {
        $this->object = $object;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function updateMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, AssetVariant>
     */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(AssetVariant $variant): void
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
        }
    }
}
