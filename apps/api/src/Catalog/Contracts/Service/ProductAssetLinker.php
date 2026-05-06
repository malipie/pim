<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Service;

use Symfony\Component\Uid\Uuid;

/**
 * Cross-context bridge for the product ↔ asset many-to-many (#440).
 *
 * Asset_Internals stores the file; Catalog_Internals owns Product
 * (a `CatalogObject` of `kind=product`). The link table sits in the
 * Catalog persistence boundary so the implementation lives there.
 *
 * Operations are idempotent — re-linking an asset that is already
 * attached is a no-op (UPSERT semantics).
 */
interface ProductAssetLinker
{
    /**
     * Attach every asset id to the product. Idempotent — duplicates
     * are silently ignored. New rows are appended at the end of the
     * existing position sequence.
     *
     * @param array<int, Uuid> $assetIds
     */
    public function linkAssetsToProduct(Uuid $productId, array $assetIds): void;

    public function unlinkAssetFromProduct(Uuid $productId, Uuid $assetId): void;

    /**
     * Return the asset ids currently attached to the product, in the
     * order they should be rendered (ASC by `position` then `created_at`).
     *
     * @return array<int, Uuid>
     */
    public function findAssetIdsForProduct(Uuid $productId): array;
}
