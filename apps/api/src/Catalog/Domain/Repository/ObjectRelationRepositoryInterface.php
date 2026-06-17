<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectRelation;
use Symfony\Component\Uid\Uuid;

/**
 * Repository for {@see ObjectRelation} rows — ADR-014 / MOD-02.
 *
 * The TenantFilter scopes every read to the active tenant. MOD-06/07
 * layer the CRUD + reverse-lookup endpoints on top of these primitives.
 */
interface ObjectRelationRepositoryInterface
{
    public function add(ObjectRelation $relation): void;

    public function remove(ObjectRelation $relation): void;

    public function findById(Uuid $id): ?ObjectRelation;

    /**
     * Links anchored at `$source` carried by `$attribute`, ordered by
     * position ASC then created_at ASC. Empty list when none exist.
     *
     * @return list<ObjectRelation>
     */
    public function findBySourceAndAttribute(CatalogObject $source, Attribute $attribute): array;

    /**
     * AUD-016 (#1632) — batch sibling of {@see self::findBySourceAndAttribute()}
     * for the export builder: all links carried by `$attribute` whose source is
     * one of `$sourceIds`, in ONE query instead of one per object. Same
     * position-then-created order so the per-source pipe-join stays
     * deterministic and round-trip-stable. Tenant filter still applies.
     *
     * @param list<string> $sourceIds source object UUIDs (RFC 4122)
     *
     * @return array<string, list<ObjectRelation>> keyed by source object UUID (RFC 4122)
     */
    public function findBySourceIdsAndAttribute(array $sourceIds, Attribute $attribute): array;

    /**
     * All links pointing at `$target` regardless of attribute — drives the
     * read-only reverse-relations panel from MOD-07.
     *
     * @return list<ObjectRelation>
     */
    public function findByTarget(CatalogObject $target): array;

    /**
     * MODR-06 (#928) — lightweight `count(*)` of incoming links. The
     * product detail page uses this to decide whether to surface the
     * "Powiązania" tab when the object has no forward relation attributes
     * (e.g. a Category that is only ever referenced from products).
     */
    public function countByTarget(CatalogObject $target): int;
}
