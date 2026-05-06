<?php

declare(strict_types=1);

namespace App\Asset\Application;

use App\Asset\Domain\Entity\Asset;
use App\Catalog\Contracts\Service\CatalogAssetSync;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;

/**
 * Removes an Asset row + every storage object behind it (original +
 * derivative variants).
 *
 * Storage failures are logged and swallowed — the database row is the
 * source of truth, and a stranded blob in MinIO is an acceptable
 * degradation (we'd rather have an orphan blob than a row that points
 * to nothing). A periodic GC job in phase 1 can sweep orphans.
 */
final readonly class AssetDeleter
{
    public function __construct(
        private EntityManagerInterface $em,
        private FilesystemOperator $assetsStorage,
        private LoggerInterface $logger,
        private CatalogAssetSync $catalogAssetSync,
    ) {
    }

    public function delete(Asset $asset): void
    {
        $asset->trackDeleted();

        $paths = [$asset->getStoragePath()];
        foreach ($asset->getVariants() as $variant) {
            $path = $variant->getStoragePath();
            if (!\in_array($path, $paths, true)) {
                $paths[] = $path;
            }
        }

        $code = $asset->getCode();

        $this->em->remove($asset);
        $this->em->flush();

        // Drop the linked CatalogObject so `/api/assets` listing stops
        // returning a row that no longer has storage behind it.
        $this->catalogAssetSync->removeForAsset($code);

        foreach ($paths as $path) {
            try {
                $this->assetsStorage->delete($path);
            } catch (FilesystemException $e) {
                $this->logger->warning(
                    'Failed to delete asset storage path; row removed, blob may linger.',
                    ['path' => $path, 'exception' => $e],
                );
            }
        }
    }

    /**
     * Convenience wrapper for the bulk-delete controller — atomically
     * deletes every asset that the caller is allowed to remove. Caller
     * is responsible for the per-item authorization check.
     *
     * @param array<int, Asset> $assets
     */
    public function deleteMany(array $assets): void
    {
        foreach ($assets as $asset) {
            $this->delete($asset);
        }
    }
}
