<?php

declare(strict_types=1);

namespace App\Asset\Application;

use App\Asset\Application\Thumbnail\ImageProcessorInterface;
use App\Asset\Contracts\Event\AssetThumbnailsRequested;
use App\Asset\Domain\Entity\AssetVariant;
use App\Asset\Domain\Repository\AssetRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * Async handler bound to the `assets-thumbnails` transport.
 *
 * Reads the original file from Flysystem into a tmp path (Imagick
 * doesn't accept stream resources for PDFs), runs the configured
 * {@see ImageProcessorInterface} to produce 200×200 + 800×800 WebP
 * derivatives, persists the bytes back into the bucket and creates
 * matching {@see AssetVariant} rows.
 *
 * On failure the asset is flipped to `ThumbnailsStatus::Failed` and
 * the exception is logged — the grid renders a placeholder for those.
 *
 * Memory hygiene: each handler invocation flushes once and clears the
 * Doctrine UoW so the FrankenPHP worker doesn't accumulate identity
 * map entries across messages (sekcja 3.10 architektury).
 */
#[AsMessageHandler]
final readonly class AssetThumbnailHandler
{
    public function __construct(
        private AssetRepositoryInterface $assets,
        private FilesystemOperator $assetsStorage,
        private ImageProcessorInterface $imageProcessor,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AssetThumbnailsRequested $message): void
    {
        $asset = $this->assets->findById($message->assetId);
        if (null === $asset) {
            $this->logger->warning('AssetThumbnailHandler: asset not found, skipping.', [
                'asset_id' => $message->assetId->toRfc4122(),
            ]);

            return;
        }

        if ('image/svg+xml' === $message->mimeType) {
            $asset->markThumbnailsReady(width: $asset->getWidth(), height: $asset->getHeight(), pageCount: null);
            $this->em->flush();
            $this->em->clear();

            return;
        }

        $tmpPath = $this->stageOriginal($message->storagePath, $message->mimeType);
        if (null === $tmpPath) {
            $asset->markThumbnailsFailed();
            $this->em->flush();
            $this->em->clear();

            return;
        }

        try {
            $processed = $this->imageProcessor->process($tmpPath, $message->mimeType);
        } catch (Throwable $e) {
            $this->logger->error('AssetThumbnailHandler: processing failed.', [
                'asset_id' => $message->assetId->toRfc4122(),
                'mime' => $message->mimeType,
                'exception' => $e,
            ]);
            $asset->markThumbnailsFailed();
            $this->em->flush();
            $this->em->clear();
            @unlink($tmpPath);

            return;
        }

        @unlink($tmpPath);

        $directory = $this->variantDirectory($message->storagePath);
        $thumbPath = $directory.'thumb.'.$processed->variantExtension;
        $mediumPath = $directory.'medium.'.$processed->variantExtension;

        try {
            $this->assetsStorage->write($thumbPath, $processed->thumbBytes);
            $this->assetsStorage->write($mediumPath, $processed->mediumBytes);
        } catch (FilesystemException $e) {
            $this->logger->error('AssetThumbnailHandler: storage write failed.', [
                'asset_id' => $message->assetId->toRfc4122(),
                'exception' => $e,
            ]);
            $asset->markThumbnailsFailed();
            $this->em->flush();
            $this->em->clear();

            return;
        }

        $thumbVariant = new AssetVariant(
            asset: $asset,
            variantCode: AssetVariant::CODE_THUMB,
            storagePath: $thumbPath,
            mimeType: $processed->variantMimeType,
            size: \strlen($processed->thumbBytes),
        );
        $mediumVariant = new AssetVariant(
            asset: $asset,
            variantCode: AssetVariant::CODE_MEDIUM,
            storagePath: $mediumPath,
            mimeType: $processed->variantMimeType,
            size: \strlen($processed->mediumBytes),
        );
        $asset->addVariant($thumbVariant);
        $asset->addVariant($mediumVariant);
        $this->em->persist($thumbVariant);
        $this->em->persist($mediumVariant);

        $asset->markThumbnailsReady(
            width: $processed->width,
            height: $processed->height,
            pageCount: $processed->pageCount,
        );

        $this->em->flush();
        $this->em->clear();
    }

    private function stageOriginal(string $storagePath, string $mimeType): ?string
    {
        try {
            $stream = $this->assetsStorage->readStream($storagePath);
        } catch (FilesystemException $e) {
            $this->logger->error('AssetThumbnailHandler: cannot read original.', [
                'storage_path' => $storagePath,
                'exception' => $e,
            ]);

            return null;
        }

        $extension = MimeTypeWhitelist::isPdf($mimeType) ? '.pdf' : '.bin';
        $tmpBase = tempnam(sys_get_temp_dir(), 'asset_');
        if (false === $tmpBase) {
            return null;
        }
        $tmpPath = $tmpBase.$extension;

        $target = fopen($tmpPath, 'w');
        if (false === $target) {
            return null;
        }
        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            if (\is_resource($target)) {
                fclose($target);
            }
            if (\is_resource($stream)) {
                fclose($stream);
            }
        }

        return $tmpPath;
    }

    private function variantDirectory(string $originalPath): string
    {
        $lastSlash = strrpos($originalPath, '/');
        if (false === $lastSlash) {
            return '';
        }

        return substr($originalPath, 0, $lastSlash + 1);
    }
}
