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

    public function findOneByScope(
        CatalogObject $object,
        Attribute $attribute,
        ?Uuid $channelId = null,
        ?string $locale = null,
    ): ?ObjectValue;

    public function save(ObjectValue $value): void;

    public function remove(ObjectValue $value): void;
}
