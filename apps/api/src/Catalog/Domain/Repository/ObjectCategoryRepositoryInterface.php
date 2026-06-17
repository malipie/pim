<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use Symfony\Component\Uid\Uuid;

/**
 * PCAT-01 (#474) — repository contract for the `objects × categories`
 * junction. Composite PK so the standard `find()` shape doesn't apply
 * — call sites slice by product (the picker dialog + form), by category
 * (reverse listing), or as a single tuple lookup.
 */
interface ObjectCategoryRepositoryInterface
{
    /**
     * All assignments for a single product, ordered by `position` ASC then
     * `created_at` ASC for deterministic UI rendering and for the
     * promote-next-primary tie-breaker (PCAT-02 DELETE handler / PCAT-03
     * `PrimaryCategoryRepairListener`).
     *
     * @return list<ObjectCategory>
     */
    public function findByProduct(CatalogObject $product): array;

    /**
     * AUD-016 (#1632) — batch sibling of {@see self::findByProduct()} for the
     * export builder: all assignments for any product in `$productIds`, in ONE
     * query instead of one per object. Same position-then-created order so the
     * per-product category pipe-join stays deterministic and round-trip-stable.
     * Tenant filter still applies.
     *
     * @param list<string> $productIds product object UUIDs (RFC 4122)
     *
     * @return array<string, list<ObjectCategory>> keyed by product object UUID (RFC 4122)
     */
    public function findByProductIds(array $productIds): array;

    /**
     * All assignments referencing a single category, ordered the same way.
     * Used by the reverse-listing endpoint (PCAT-06) and by the cache
     * invalidator when a category-level change should burst per-product
     * caches.
     *
     * @return list<ObjectCategory>
     */
    public function findByCategory(CatalogObject $category): array;

    /**
     * Single tuple lookup. Returns `null` when the (product, category)
     * pair has no row — used by POST single-add to detect idempotent
     * conflict.
     */
    public function findOne(CatalogObject $product, CatalogObject $category): ?ObjectCategory;

    /**
     * The current primary assignment for a product, or `null` when the
     * product has no assignments at all (legal: resolver falls back to
     * global ObjectType groups). Backed by the partial unique index on
     * `(object_id) WHERE is_primary = true`.
     */
    public function findPrimary(CatalogObject $product): ?ObjectCategory;

    /**
     * Atomic full-replace of a product's assignments. Wipes all existing
     * rows for the product, re-inserts the supplied list, and marks the
     * row matching `$primaryId` as primary in the same transaction.
     *
     * Wraps DELETE + INSERT in `wrapInTransaction()` so the partial
     * unique index never sees two primary rows for the same product
     * mid-flush. Caller MUST ensure `$primaryId` is in `$categoryIds`
     * (or both null/empty) — controller-level validation guards this in
     * PCAT-02; the repo asserts defensively but does not validate
     * business rules.
     *
     * @param list<Uuid> $categoryIds
     */
    public function replaceForProduct(CatalogObject $product, array $categoryIds, ?Uuid $primaryId): void;

    public function save(ObjectCategory $assignment): void;

    public function remove(ObjectCategory $assignment): void;
}
