<?php

declare(strict_types=1);

namespace App\Asset\Application;

use App\Asset\Application\Exception\DuplicateAssetException;
use App\Asset\Contracts\Event\AssetThumbnailsRequested;
use App\Asset\Domain\Entity\Asset;
use App\Asset\Domain\Entity\AssetVariant;
use App\Asset\Domain\Repository\AssetRepositoryInterface;
use App\Catalog\Contracts\Service\CatalogAssetSync;
use App\Shared\Application\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Uid\Uuid;

use const PATHINFO_FILENAME;

/**
 * Uploads a binary file to the assets storage and creates the matching
 * `Asset` + `original` `AssetVariant` rows.
 *
 * Storage layout: `<tenant-uuid>/<asset-uuid>/original.<ext>` — tenant
 * UUID prefix gives a coarse path-level isolation that mirrors the
 * Doctrine TenantFilter on the database side.
 *
 * The pipeline (#438):
 *   1. SHA-256 the source bytes streaming-style (constant memory).
 *   2. Look the hash up against the tenant — bail with
 *      {@see DuplicateAssetException} if a row already exists, leaving
 *      the bucket untouched.
 *   3. Stream the bytes into the bucket, persist Asset + original
 *      variant.
 *   4. Capture image dimensions (`getimagesize` for raster formats)
 *      synchronously; page count + thumbnail variants are produced by
 *      the async {@see AssetThumbnailHandler}.
 *   5. Dispatch {@see AssetThumbnailsRequested} on the
 *      `assets-thumbnails` async transport.
 *
 * The smoke-test CLI (`pim:asset:upload`) calls this same path, so any
 * regression manifests in both surfaces.
 */
final readonly class AssetUploader
{
    public function __construct(
        private FilesystemOperator $assetsStorage,
        private EntityManagerInterface $em,
        private TenantContext $tenantContext,
        private AssetRepositoryInterface $assets,
        private MessageBusInterface $bus,
        private SluggerInterface $slugger,
        private CatalogAssetSync $catalogAssetSync,
    ) {
    }

    /**
     * @param array<int, string> $tags
     */
    public function upload(File $file, ?string $code = null, array $tags = []): Asset
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new RuntimeException('AssetUploader requires an active TenantContext.');
        }

        $sourcePath = $file->getPathname();
        $contentHash = hash_file('sha256', $sourcePath);
        if (false === $contentHash) {
            throw new RuntimeException(\sprintf('Failed to hash uploaded file %s.', $sourcePath));
        }

        $existing = $this->assets->findByContentHash($contentHash, $tenant);
        if (null !== $existing) {
            throw new DuplicateAssetException($existing->getId(), $existing->getCode());
        }

        $resolvedCode = $this->resolveCode($code, $file, $tenant);

        $assetId = Uuid::v7();
        $rawExtension = $file->getExtension();
        $extension = '' !== $rawExtension ? $rawExtension : 'bin';
        $storagePath = \sprintf(
            '%s/%s/original.%s',
            $tenant->getId()->toRfc4122(),
            $assetId->toRfc4122(),
            $extension,
        );

        $stream = fopen($sourcePath, 'r');
        if (false === $stream) {
            throw new RuntimeException(\sprintf('Failed to open uploaded file %s for reading.', $sourcePath));
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

        $mimeType = $file->getMimeType() ?? 'application/octet-stream';
        [$width, $height] = $this->probeDimensions($sourcePath, $mimeType);

        $asset = new Asset(
            code: $resolvedCode,
            originalFilename: $file->getFilename(),
            mimeType: $mimeType,
            size: $size,
            storagePath: $storagePath,
            id: $assetId,
            contentHash: $contentHash,
            width: $width,
            height: $height,
            tags: $tags,
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

        // Mirror the new Asset into a CatalogObject(kind=asset) so the
        // `/api/assets` grid (which lists CatalogObject rows) shows the
        // upload. Storage-side fields go straight into
        // `attributes_indexed` (denormalised cache; no EAV rows needed
        // — same shape the demo seeder writes).
        $catalogObjectId = $this->catalogAssetSync->syncFromUploadedAsset(
            assetId: $asset->getId(),
            code: $resolvedCode,
            indexedAttributes: $this->buildIndexedAttributes($asset),
        );
        $asset->linkToObject($catalogObjectId);
        $this->em->flush();

        $this->bus->dispatch(new AssetThumbnailsRequested(
            assetId: $asset->getId(),
            tenantId: $tenant->getId(),
            storagePath: $storagePath,
            mimeType: $mimeType,
        ));

        return $asset;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildIndexedAttributes(Asset $asset): array
    {
        return [
            'mime' => $asset->getMimeType(),
            'filename' => $asset->getOriginalFilename(),
            'previewUrl' => \sprintf('/api/assets/%s/preview', $asset->getId()->toRfc4122()),
            'thumbnailsStatus' => $asset->getThumbnailsStatus()->value,
            'tags' => $asset->getTags(),
            'size' => $asset->getSize(),
            'width' => $asset->getWidth(),
            'height' => $asset->getHeight(),
            'pageCount' => $asset->getPageCount(),
        ];
    }

    /**
     * Read the stored bytes back. Used by smoke tests + future signed-URL
     * generation.
     */
    public function read(Asset $asset): string
    {
        return $this->assetsStorage->read($asset->getStoragePath());
    }

    private function resolveCode(?string $code, File $file, \App\Shared\Domain\Tenant $tenant): string
    {
        if (null !== $code && '' !== trim($code)) {
            return $code;
        }

        $base = pathinfo($file->getFilename(), PATHINFO_FILENAME);
        $slug = strtolower((string) $this->slugger->slug($base));
        if ('' === $slug) {
            $slug = 'asset';
        }

        $candidate = $slug;
        $suffix = 1;
        while (null !== $this->assets->findByCode($candidate, $tenant)) {
            ++$suffix;
            $candidate = $slug.'-'.$suffix;
        }

        return $candidate;
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function probeDimensions(string $path, string $mimeType): array
    {
        if (!MimeTypeWhitelist::isImage($mimeType) || 'image/svg+xml' === $mimeType) {
            return [null, null];
        }

        $info = @getimagesize($path);
        if (false === $info) {
            return [null, null];
        }

        return [$info[0], $info[1]];
    }
}
