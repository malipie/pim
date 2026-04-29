<?php

declare(strict_types=1);

namespace App\Asset\Application;

use App\Asset\Domain\Entity\Asset;
use App\Asset\Domain\Entity\AssetVariant;
use App\Shared\Application\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Uid\Uuid;

/**
 * Uploads a binary file to the assets storage and creates the matching
 * `Asset` + `original` `AssetVariant` rows.
 *
 * Storage layout: `<tenant-uuid>/<asset-uuid>/original.<ext>` — tenant
 * UUID prefix gives a coarse path-level isolation that mirrors the
 * Doctrine TenantFilter on the database side. Production deployments
 * with sensitive media should additionally enforce isolation through a
 * bucket policy (out of MVP scope).
 *
 * The metadata (EXIF, dimensions) is intentionally minimal in MVP —
 * just `size_bytes` + the upstream filename. Phase 1 adds an EXIF
 * reader + image dimensions extraction via getimagesize() / Imagick.
 */
final readonly class AssetUploader
{
    public function __construct(
        private FilesystemOperator $assetsStorage,
        private EntityManagerInterface $em,
        private TenantContext $tenantContext,
    ) {
    }

    /**
     * Upload a `File` to storage and persist the matching Asset + Variant.
     * Returns the persisted Asset.
     *
     * `$code` is the tenant-unique slug used in the API path; the caller
     * is responsible for uniqueness (UNIQUE index will reject collisions).
     */
    public function upload(File $file, string $code): Asset
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new RuntimeException('AssetUploader requires an active TenantContext.');
        }

        $assetId = Uuid::v7();
        $rawExtension = $file->getExtension();
        $extension = '' !== $rawExtension ? $rawExtension : 'bin';
        $storagePath = \sprintf(
            '%s/%s/original.%s',
            $tenant->getId()->toRfc4122(),
            $assetId->toRfc4122(),
            $extension,
        );

        $stream = fopen($file->getPathname(), 'r');
        if (false === $stream) {
            throw new RuntimeException(\sprintf('Failed to open uploaded file %s for reading.', $file->getPathname()));
        }
        try {
            $this->assetsStorage->writeStream($storagePath, $stream);
        } finally {
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }

        $size = $file->getSize();
        if (false === $size) {
            $size = 0;
        }

        $asset = new Asset(
            code: $code,
            originalFilename: $file->getFilename(),
            mimeType: $file->getMimeType() ?? 'application/octet-stream',
            size: $size,
            storagePath: $storagePath,
            id: $assetId,
        );

        $original = new AssetVariant(
            asset: $asset,
            variantCode: AssetVariant::CODE_ORIGINAL,
            storagePath: $storagePath,
            mimeType: $asset->getMimeType(),
            size: $size,
        );
        $asset->addVariant($original);

        $this->em->persist($asset);
        $this->em->persist($original);
        $this->em->flush();

        return $asset;
    }

    /**
     * Read the stored bytes back. Used by smoke tests + future signed-URL
     * generation in #41.
     */
    public function read(Asset $asset): string
    {
        return $this->assetsStorage->read($asset->getStoragePath());
    }
}
