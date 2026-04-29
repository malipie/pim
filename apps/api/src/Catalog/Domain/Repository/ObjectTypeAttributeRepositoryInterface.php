<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;

interface ObjectTypeAttributeRepositoryInterface
{
    /**
     * @return list<ObjectTypeAttribute>
     */
    public function findByObjectType(ObjectType $objectType): array;

    public function findOne(ObjectType $objectType, Attribute $attribute): ?ObjectTypeAttribute;

    public function save(ObjectTypeAttribute $junction): void;

    public function remove(ObjectTypeAttribute $junction): void;
}
