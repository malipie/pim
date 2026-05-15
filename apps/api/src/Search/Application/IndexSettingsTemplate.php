<?php

declare(strict_types=1);

namespace App\Search\Application;

use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use LogicException;

/**
 * Per-`ObjectKind` Meilisearch index settings template (#49 / 0.5.1).
 *
 * VIEW-38 (#579) — the product index's `filterableAttributes` is now
 * built dynamically: a small reserved set (system-owned columns Meili
 * always needs) unioned with the distinct codes of every
 * `attributes.is_filterable=true` row. Operators flip the toggle in
 * Settings → Attributes and the next request that refreshes settings
 * (postPersist/postUpdate listener) exposes the new filter target
 * without a deploy.
 *
 * Other kinds (Category / Asset / Brand) keep the static list — they
 * carry no operator-extensible attribute slot in MVP.
 */
final class IndexSettingsTemplate
{
    /**
     * Reserved filterable fields the product index always carries
     * regardless of operator-defined attributes. `tenantId` enforces
     * the multi-tenant isolation filter in `CatalogSearchService`;
     * `kind` / `status` / `enabled` / `objectTypeId` drive UI surfaces
     * (variant toggle, status pills, type-scoped saved views);
     * `sync_status_aggregate` is the badge column;
     * `completeness_pct` powers the Red (<50%) smart preset;
     * `category` is denormalized by the indexer from `object_categories`
     * (not a user attribute, but a join surface needed for the
     * `IS EMPTY category` preset).
     *
     * @var list<string>
     */
    private const array PRODUCT_RESERVED_FILTERABLE = [
        'tenantId', 'kind', 'status', 'enabled', 'objectTypeId',
        'completeness_pct', 'sync_status_aggregate', 'category',
        // `parentId` exposed so the variant-tree view (only masters) can
        // mirror its `parent_id IS NULL` filter on cross-page selection
        // through Meili (`BulkSelectionController` reads `variants_mode`
        // from the body and emits the matching filter).
        'parentId',
    ];

    public function __construct(
        private readonly ?AttributeRepositoryInterface $attributes = null,
    ) {
    }

    /**
     * Index name per kind. `kind=custom` is reserved for Faza 2/3
     * (per ADR-009) — the indexer skips custom kinds in MVP.
     */
    public static function indexName(ObjectKind $kind): string
    {
        return match ($kind) {
            ObjectKind::Product => 'products',
            ObjectKind::Category => 'categories',
            ObjectKind::Asset => 'assets',
            ObjectKind::Brand => 'brands',
            ObjectKind::Custom => throw new LogicException('Custom kind has no MVP index — phase 2/3 unlock.'),
        };
    }

    /**
     * @return array<string, mixed> settings payload accepted by
     *                              `Meilisearch\Endpoints\Indexes::updateSettings`
     */
    public function settingsFor(ObjectKind $kind): array
    {
        return match ($kind) {
            ObjectKind::Product => [
                'searchableAttributes' => ['code', 'name', 'sku', 'brand', 'description'],
                'filterableAttributes' => $this->productFilterableAttributes(),
                'sortableAttributes' => ['createdAt', 'updatedAt', 'name', 'price'],
                'displayedAttributes' => ['*'],
                'rankingRules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
                // Meili's default `maxTotalHits` (1000) caps cross-page
                // selection at the first page and silently truncates the
                // result. Operator's "Zaznacz wszystkie pasujące" loop
                // page-by-page tops out at 1000 even though tenants
                // routinely run 5–10× that. Raised to match the bulk
                // selection HARD_CAP (10 000) — `BulkSelectionController`
                // already enforces that as the absolute ceiling.
                'pagination' => ['maxTotalHits' => 10_000],
            ],
            ObjectKind::Category => [
                'searchableAttributes' => ['code', 'name', 'path', 'seo_title'],
                'filterableAttributes' => ['tenantId', 'kind', 'parentId', 'objectTypeId'],
                'sortableAttributes' => ['createdAt', 'updatedAt', 'name'],
                'displayedAttributes' => ['*'],
                'rankingRules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
            ],
            ObjectKind::Asset => [
                'searchableAttributes' => ['code', 'name', 'alt_text', 'caption'],
                'filterableAttributes' => ['tenantId', 'kind', 'mime_type', 'objectTypeId'],
                'sortableAttributes' => ['createdAt', 'updatedAt'],
                'displayedAttributes' => ['*'],
                'rankingRules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
            ],
            ObjectKind::Brand => [
                'searchableAttributes' => ['code', 'name', 'description'],
                'filterableAttributes' => ['tenantId', 'kind', 'enabled', 'objectTypeId'],
                'sortableAttributes' => ['createdAt', 'updatedAt', 'name'],
                'displayedAttributes' => ['*'],
                'rankingRules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
            ],
            ObjectKind::Custom => throw new LogicException('Custom kind has no MVP settings template.'),
        };
    }

    /**
     * @return list<string>
     */
    private function productFilterableAttributes(): array
    {
        $dynamic = null === $this->attributes ? [] : $this->attributes->filterableCodes();
        $merged = array_values(array_unique([...self::PRODUCT_RESERVED_FILTERABLE, ...$dynamic]));
        sort($merged);

        return $merged;
    }

    /**
     * @return list<ObjectKind>
     */
    public static function indexedKinds(): array
    {
        return [ObjectKind::Product, ObjectKind::Category, ObjectKind::Asset, ObjectKind::Brand];
    }
}
