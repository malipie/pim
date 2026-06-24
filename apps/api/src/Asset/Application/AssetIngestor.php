<?php

declare(strict_types=1);

namespace App\Asset\Application;

use App\Asset\Application\Exception\DuplicateAssetException;
use App\Asset\Contracts\AssetIngestorInterface;
use App\Asset\Contracts\AssetIngestResult;
use App\Asset\Contracts\Exception\UnsupportedMediaFormatException;
use App\Asset\Domain\Repository\AssetRepositoryInterface;
use App\Shared\Application\TenantContext;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\File;

use const PATHINFO_FILENAME;

/**
 * IMP2-1.12 — default {@see AssetIngestorInterface}: magic-byte validation
 * (jpg/png/webp only, content sniff — never trusts the source's declared
 * type), content-hash dedup, and storage via {@see AssetUploader} (which
 * owns the bucket write + Asset row + catalog mirror + thumbnail dispatch).
 *
 * Dedup is checked up front so a repeated URL returns `reused=true` without
 * touching the bucket; the DuplicateAssetException catch covers the race
 * where two concurrent ingests hash-collide between the pre-check and the
 * uploader's own guard.
 */
final readonly class AssetIngestor implements AssetIngestorInterface
{
    /** Accepted image magic numbers → canonical extension. */
    private const string EXT_JPEG = 'jpg';
    private const string EXT_PNG = 'png';
    private const string EXT_WEBP = 'webp';

    public function __construct(
        private AssetUploader $uploader,
        private AssetRepositoryInterface $assets,
        private TenantContext $tenantContext,
    ) {
    }

    public function ingest(string $absolutePath, string $originalFilename, ?string $folderCode = null): AssetIngestResult
    {
        $extension = $this->sniffExtension($absolutePath, $originalFilename);

        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new RuntimeException('AssetIngestor requires an active TenantContext.');
        }

        $hash = hash_file('sha256', $absolutePath);
        if (false === $hash) {
            throw new RuntimeException(\sprintf('Failed to hash media file "%s".', $absolutePath));
        }
        $existing = $this->assets->findByContentHash($hash, $tenant);
        if (null !== $existing) {
            return new AssetIngestResult($existing->getId(), true);
        }

        // AssetUploader derives the stored filename + extension from the
        // File path, so stage a copy named after the source with the
        // sniffed extension (a raw download temp path has neither).
        $base = pathinfo($originalFilename, PATHINFO_FILENAME);
        $safeBase = preg_replace('/[^A-Za-z0-9_-]+/', '-', $base) ?? '';
        $safeBase = trim($safeBase, '-');
        if ('' === $safeBase) {
            $safeBase = 'image';
        }
        $tmpDir = sys_get_temp_dir().'/pim-ingest-'.bin2hex(random_bytes(8));
        if (!mkdir($tmpDir) && !is_dir($tmpDir)) {
            throw new RuntimeException(\sprintf('Failed to create ingest temp dir "%s".', $tmpDir));
        }
        $staged = $tmpDir.'/'.$safeBase.'.'.$extension;

        try {
            if (!copy($absolutePath, $staged)) {
                throw new RuntimeException(\sprintf('Failed to stage media file "%s".', $absolutePath));
            }
            $asset = $this->uploader->upload(new File($staged), folderCode: $folderCode);

            return new AssetIngestResult($asset->getId(), false);
        } catch (DuplicateAssetException $exception) {
            // Race: another ingest stored the same bytes between the
            // pre-check and the uploader's guard — reuse it.
            return new AssetIngestResult($exception->existingAssetId, true);
        } finally {
            if (is_file($staged)) {
                @unlink($staged);
            }
            if (is_dir($tmpDir)) {
                @rmdir($tmpDir);
            }
        }
    }

    /**
     * Content-sniff the accepted image formats by magic bytes. The source's
     * declared content-type / URL extension is never trusted.
     *
     * @throws UnsupportedMediaFormatException
     */
    private function sniffExtension(string $absolutePath, string $originalFilename): string
    {
        $handle = fopen($absolutePath, 'r');
        if (false === $handle) {
            throw new RuntimeException(\sprintf('Failed to open media file "%s".', $absolutePath));
        }
        try {
            $head = fread($handle, 16);
        } finally {
            fclose($handle);
        }
        if (false === $head || \strlen($head) < 12) {
            throw UnsupportedMediaFormatException::forFilename($originalFilename);
        }

        if (str_starts_with($head, "\xFF\xD8\xFF")) {
            return self::EXT_JPEG;
        }
        if (str_starts_with($head, "\x89PNG\r\n\x1A\n")) {
            return self::EXT_PNG;
        }
        if (str_starts_with($head, 'RIFF') && 'WEBP' === substr($head, 8, 4)) {
            return self::EXT_WEBP;
        }

        throw UnsupportedMediaFormatException::forFilename($originalFilename);
    }
}
