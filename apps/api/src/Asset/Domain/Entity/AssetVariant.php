<?php

declare(strict_types=1);

namespace App\Asset\Domain\Entity;

use App\Asset\Infrastructure\Doctrine\Repository\AssetVariantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Stored derivative of an {@see Asset} — `original`, `thumb`, `medium`,
 * `webp_1920`, etc.
 *
 * In MVP every Asset gets a single `original` variant created at upload.
 * Phase 1 transforms (thumbnail, medium, webp) come with the
 * transformation pipeline and a worker.
 *
 * Tenant scope is inherited via the parent Asset; no own `tenant_id`
 * column. The table joins `INFRA_TABLES` allowlist in
 * `pim:tenant:audit`.
 */
#[ORM\Entity(repositoryClass: AssetVariantRepository::class)]
#[ORM\Table(name: 'asset_variants')]
#[ORM\UniqueConstraint(name: 'asset_variants_asset_code_uniq', columns: ['asset_id', 'variant_code'])]
#[ORM\Index(name: 'asset_variants_asset_idx', columns: ['asset_id'])]
class AssetVariant
{
    public const string CODE_ORIGINAL = 'original';
    public const string CODE_THUMB = 'thumb';
    public const string CODE_MEDIUM = 'medium';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Asset::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Asset $asset;

    #[ORM\Column(name: 'variant_code', type: Types::STRING, length: 32)]
    #[Assert\NotBlank]
    private string $variantCode;

    #[ORM\Column(name: 'storage_path', type: Types::STRING, length: 1024)]
    private string $storagePath;

    #[ORM\Column(name: 'mime_type', type: Types::STRING, length: 128)]
    private string $mimeType;

    #[ORM\Column(type: Types::BIGINT)]
    private int $size;

    public function __construct(
        Asset $asset,
        string $variantCode,
        string $storagePath,
        string $mimeType,
        int $size,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->asset = $asset;
        $this->variantCode = $variantCode;
        $this->storagePath = $storagePath;
        $this->mimeType = $mimeType;
        $this->size = $size;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getAsset(): Asset
    {
        return $this->asset;
    }

    public function getVariantCode(): string
    {
        return $this->variantCode;
    }

    public function getStoragePath(): string
    {
        return $this->storagePath;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getSize(): int
    {
        return $this->size;
    }
}
