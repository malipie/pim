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
     * All links pointing at `$target` regardless of attribute — drives the
     * read-only reverse-relations panel from MOD-07.
     *
     * @return list<ObjectRelation>
     */
    public function findByTarget(CatalogObject $target): array;
}
