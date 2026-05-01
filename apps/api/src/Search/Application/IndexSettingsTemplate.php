<?php

declare(strict_types=1);

namespace App\Search\Application;

use App\Catalog\Domain\ObjectKind;
use LogicException;

/**
 * Per-`ObjectKind` Meilisearch index settings template (#49 / 0.5.1).
 *
 * Settings (searchable / filterable / sortable / faceting) are pinned
 * here so each kind's index opens with a known shape — the package
 * quirk in lessons #0.0.x calls out that **facetable attributes must
 * be declared explicitly** or `?facets=` returns empty without an
 * error, which is impossible to catch in CI without a contract test.
 *
 * Per-attribute config is intentionally narrow in MVP — the indexer
 * (#50) writes these via `Index::updateSettings()` once at boot. A
 * future per-tenant overlay can read overrides from
 * `object_type.search_config` JSONB (post-ADR-009) without touching
 * this template.
 *
 * Indexes per kind (`products`, `categories`, `assets`) — singular
 * `objects` index would force per-kind branching at every read site;
 * three small indexes keep filter mental models clean.
 */
final class IndexSettingsTemplate
{
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
                'filterableAttributes' => ['tenantId', 'kind', 'status', 'enabled', 'objectTypeId', 'brand', 'category'],
                'sortableAttributes' => ['createdAt', 'updatedAt', 'name'],
                'displayedAttributes' => ['*'],
                'rankingRules' => ['words', 'typo', 'proximity', 'attribute', 'sort', 'exactness'],
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
     * @return list<ObjectKind>
     */
    public static function indexedKinds(): array
    {
        return [ObjectKind::Product, ObjectKind::Category, ObjectKind::Asset, ObjectKind::Brand];
    }
}
