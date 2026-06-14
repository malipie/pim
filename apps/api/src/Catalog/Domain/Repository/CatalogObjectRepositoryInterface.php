<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface CatalogObjectRepositoryInterface
{
    public function findById(Uuid $id): ?CatalogObject;

    /**
     * Batch fetch by RFC4122 id strings. Used by the search batch indexer
     * (PROD-03) to materialise a request-scoped queue of pending upserts
     * in one query instead of N round-trips. Tenant filter still applies.
     *
     * @param list<string> $idsRfc4122
     *
     * @return list<CatalogObject>
     */
    public function findByIds(array $idsRfc4122): array;

    public function findByCode(string $code, ObjectKind $kind, Tenant $tenant): ?CatalogObject;

    /**
     * IMP2-1.8 (#1471) — resolve an object by `code` within a set of allowed
     * ObjectTypes (a relation attribute's `relationTargetObjectTypeIds`),
     * tenant-scoped. An empty `$objectTypeIds` matches any type in the
     * tenant. Returns the first match or null. Used by the import relation
     * step; the tenant filter guarantees a code that only exists in another
     * tenant resolves to null (cross-tenant isolation).
     *
     * @param list<string> $objectTypeIds UUID strings; empty = any type
     */
    public function findByCodeInObjectTypes(string $code, array $objectTypeIds, Tenant $tenant): ?CatalogObject;

    /**
     * IMP2-1.8 (#1471) — children (variants) of the given parents,
     * tenant-scoped, ordered by `code` ASC for a deterministic export
     * fan-out. Drives `include_variants` on the export runner.
     *
     * @param list<string> $parentIdsRfc4122
     *
     * @return list<CatalogObject>
     */
    public function findChildrenByParentIds(array $parentIdsRfc4122, Tenant $tenant): array;

    /**
     * @return list<CatalogObject>
     */
    public function findByKind(ObjectKind $kind, Tenant $tenant): array;

    /**
     * Objects of a specific ObjectType within a tenant (EXR-05).
     *
     * Generalises {@see self::findByKind()} from the built-in Product kind to
     * any ObjectType — products resolve their built-in type, custom modules
     * their own. Tenant filter still applies.
     *
     * @return list<CatalogObject>
     */
    public function findByObjectType(ObjectType $objectType, Tenant $tenant): array;

    public function save(CatalogObject $object): void;

    public function remove(CatalogObject $object): void;
}
