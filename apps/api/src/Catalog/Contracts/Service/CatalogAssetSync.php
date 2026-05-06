<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Service;

use Symfony\Component\Uid\Uuid;

/**
 * Cross-context bridge between Asset and Catalog (#438).
 *
 * The Asset bounded context owns storage details (path, mime, size,
 * derivatives) but the read surface for `/api/assets` lists
 * {@see \App\Catalog\Domain\Entity\CatalogObject} rows of `kind=asset`.
 * Without a `CatalogObject` linked to a freshly uploaded `Asset`, the
 * grid stays empty.
 *
 * Asset_Internals cannot reach into Catalog_Internals to dispatch
 * `CreateCatalogObjectCommand` (Deptrac, ADR-0013), so this contract
 * gives the upload pipeline a public entry point.
 *
 * The implementation lives in Catalog_Internals and:
 *   - looks up the built-in `kind=asset` `ObjectType` for the active
 *     tenant,
 *   - upserts a `CatalogObject` (creates a new row if `assetId` is not
 *     yet linked, otherwise refreshes the indexed attributes on the
 *     existing one),
 *   - writes `$indexedAttributes` straight into `objects.attributes_
 *     indexed` (denormalised cache; the upload pipeline does not need
 *     full EAV rows for storage-side fields like `mime`, `filename`,
 *     `previewUrl`, `thumbnailsStatus`).
 *
 * Returns the persisted `CatalogObject` id so the caller can pin it
 * onto `Asset::linkToObject(...)`.
 */
interface CatalogAssetSync
{
    /**
     * @param array<string, mixed> $indexedAttributes JSON-friendly map
     *                                                written verbatim
     *                                                to `attributes_
     *                                                indexed`
     */
    public function syncFromUploadedAsset(
        Uuid $assetId,
        string $code,
        array $indexedAttributes,
    ): Uuid;

    /**
     * Remove the CatalogObject linked to the given asset code (if any).
     * Called by `AssetDeleter` so the grid no longer sees a row that
     * points at a deleted Asset / missing storage blob.
     */
    public function removeForAsset(string $code): void;
}
