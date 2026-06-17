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

    /**
     * IMP2-2.6 — one keyset page of ROOT objects (no parent) of a type, ordered
     * by id, for the bulk export path; mirrors `include_variants=off` (masters
     * only). The caller walks pages with `id > :afterId` and `EntityManager::clear()`s
     * between them so a 50k export stays in constant memory.
     *
     * @return list<CatalogObject>
     */
    public function findRootObjectsAfter(ObjectType $objectType, Tenant $tenant, ?Uuid $afterId, int $limit): array;

    /**
     * IMP2-2.6 — count the root objects of a type without hydrating them
     * (COUNT(*)), so the export progress total never loads the whole set.
     */
    public function countRootObjectsByType(ObjectType $objectType, Tenant $tenant): int;

    /**
     * AUD-015 (#1632) — root object ids (no parent) of a type, ascending, as
     * RFC4122 strings WITHOUT hydrating entities. Backs the scope-All export
     * "id plan": the runner pages the hydration over these ids
     * ({@see self::findByIds()}) so the full object graph never lives in memory
     * at once, regardless of include_variants. Tenant filter still applies.
     *
     * @return list<string>
     */
    public function findRootObjectIds(ObjectType $objectType, Tenant $tenant): array;

    /**
     * AUD-015 (#1632) — the subset of `$idsRfc4122` that are ROOT objects (no
     * parent), as RFC4122 strings, WITHOUT hydration. Lets the export size /
     * fan out Selected & Filter scopes from an id set without loading the
     * objects. Tenant filter still applies.
     *
     * @param list<string> $idsRfc4122
     *
     * @return list<string>
     */
    public function filterRootObjectIds(array $idsRfc4122, Tenant $tenant): array;

    /**
     * AUD-015 (#1632) — id-only sibling of {@see self::findChildrenByParentIds()}
     * for the export "id plan": child (variant) ids grouped by parent, ordered
     * by `code` ASC for the deterministic master-then-variants fan-out the
     * golden round-trip relies on. No entity hydration. Tenant filter applies.
     *
     * @param list<string> $parentIdsRfc4122
     *
     * @return array<string, list<string>> parent id => ordered child ids (RFC 4122)
     */
    public function findChildIdsByParentIds(array $parentIdsRfc4122, Tenant $tenant): array;

    public function save(CatalogObject $object): void;

    public function remove(CatalogObject $object): void;
}
