<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use Symfony\Component\Uid\Uuid;

interface ObjectValueRepositoryInterface
{
    public function findById(Uuid $id): ?ObjectValue;

    /**
     * @return list<ObjectValue>
     */
    public function findByObject(CatalogObject $object): array;

    /**
     * Batch-load ObjectValue rows for multiple objects in a single query.
     * Used by the collection overlay provider to avoid N+1 queries.
     *
     * @param list<Uuid> $objectIds
     *
     * @return array<string, list<ObjectValue>> keyed by object UUID (RFC 4122)
     */
    public function findByObjectIds(array $objectIds): array;

    public function findOneByScope(
        CatalogObject $object,
        Attribute $attribute,
        ?Uuid $channelId = null,
        ?string $locale = null,
    ): ?ObjectValue;

    public function save(ObjectValue $value): void;

    public function remove(ObjectValue $value): void;
}
