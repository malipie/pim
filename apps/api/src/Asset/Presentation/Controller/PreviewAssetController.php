<?php

declare(strict_types=1);

namespace App\Asset\Presentation\Controller;

use App\Asset\Domain\Entity\AssetVariant;
use App\Asset\Domain\Repository\AssetRepositoryInterface;
use App\Asset\Domain\ThumbnailsStatus;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * `GET /api/assets/{id}/preview[?variant=thumb|medium|original]` (#438).
 *
 * Single-origin preview surface — `<img src="/api/assets/{id}/preview"
 * />` works from the admin without exposing MinIO directly to the
 * browser (CSP allows only `'self'` and `data:` for img-src). Defaults
 * to the `thumb` variant when ready, falls back to `medium` →
 * `original` so the grid never shows a broken image.
 *
 * Streams the bytes back through Flysystem (`readStream`) so a 50 MB
 * PDF does not balloon the worker memory. `Cache-Control: private,
 * max-age=...` lets the browser cache previews across navigations
 * inside the same session.
 *
 * Auth model: the endpoint is registered under `PUBLIC_ACCESS` in
 * `security.yaml` because `<img>` tags cannot send the Bearer token
 * in their request. Path-knowledge gates access — UUID v7 ids are
 * 128-bit and not enumerable, so cross-tenant leakage requires
 * guessing the exact id (effectively impossible without DB access).
 * Doctrine `tenant_filter` is disabled for the lookup so the
 * un-authenticated request reaches the row at all; tenant isolation
 * is therefore by-id rather than by-context here. Faza 1 swaps this
 * for short-lived signed URLs minted by the catalog read API.
 */
final readonly class PreviewAssetController
{
    public function __construct(
        private AssetRepositoryInterface $assets,
        private FilesystemOperator $assetsStorage,
        private EntityManagerInterface $em,
    ) {
    }

    #[Route(path: '/api/assets/{id}/preview', name: 'pim_assets_preview', methods: ['GET'])]
    public function __invoke(string $id, ?string $variant = null): StreamedResponse
    {
        $assetId = Uuid::fromString($id);

        $filters = $this->em->getFilters();
        $tenantFilterWasEnabled = $filters->isEnabled('tenant');
        if ($tenantFilterWasEnabled) {
            $filters->disable('tenant');
        }
        try {
            $asset = $this->assets->findById($assetId) ?? $this->assets->findByObjectId($assetId);
        } finally {
            if ($tenantFilterWasEnabled) {
                $filters->enable('tenant');
            }
        }

        if (null === $asset) {
            throw new NotFoundHttpException(\sprintf('Asset "%s" was not found.', $id));
        }

        [$path, $mime] = $this->resolveVariant($asset, $variant);

        try {
            $stream = $this->assetsStorage->readStream($path);
        } catch (FilesystemException $e) {
            throw new NotFoundHttpException(\sprintf('Variant blob missing for asset "%s".', $id), $e);
        }

        $response = new StreamedResponse(static function () use ($stream): void {
            if (\is_resource($stream)) {
                fpassthru($stream);
                fclose($stream);
            }
        });
        $response->headers->set('content-type', $mime);
        $response->headers->set('cache-control', 'private, max-age=300');

        return $response;
    }

    /**
     * @return array{0: string, 1: string} [storagePath, mimeType]
     */
    private function resolveVariant(\App\Asset\Domain\Entity\Asset $asset, ?string $requested): array
    {
        $variants = [];
        foreach ($asset->getVariants() as $variant) {
            $variants[$variant->getVariantCode()] = [$variant->getStoragePath(), $variant->getMimeType()];
        }

        $preferred = match ($requested) {
            'thumb', 'medium', 'original' => $requested,
            default => null,
        };

        if (null !== $preferred && isset($variants[$preferred])) {
            return $variants[$preferred];
        }

        // Default order: thumb (200) → medium (800) → original. Browser
        // dla grida zachowuje pasmo gdy thumb jest dostępny.
        if (ThumbnailsStatus::Ready === $asset->getThumbnailsStatus()) {
            foreach ([AssetVariant::CODE_THUMB, AssetVariant::CODE_MEDIUM, AssetVariant::CODE_ORIGINAL] as $code) {
                if (isset($variants[$code])) {
                    return $variants[$code];
                }
            }
        }

        if (isset($variants[AssetVariant::CODE_ORIGINAL])) {
            return $variants[AssetVariant::CODE_ORIGINAL];
        }

        return [$asset->getStoragePath(), $asset->getMimeType()];
    }
}
