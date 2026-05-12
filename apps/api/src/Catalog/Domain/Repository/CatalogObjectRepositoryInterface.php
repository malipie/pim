<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\CatalogObject;
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
     * @return list<CatalogObject>
     */
    public function findByKind(ObjectKind $kind, Tenant $tenant): array;

    public function save(CatalogObject $object): void;

    public function remove(CatalogObject $object): void;
}
