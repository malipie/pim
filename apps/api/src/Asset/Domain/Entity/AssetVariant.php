<?php

declare(strict_types=1);

namespace App\Asset\Domain\Entity;

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
class AssetVariant
{
    public const string CODE_ORIGINAL = 'original';
    public const string CODE_THUMB = 'thumb';
    public const string CODE_MEDIUM = 'medium';

    private Uuid $id;

    private Asset $asset;

    #[Assert\NotBlank]
    private string $variantCode;

    private string $storagePath;

    private string $mimeType;

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
