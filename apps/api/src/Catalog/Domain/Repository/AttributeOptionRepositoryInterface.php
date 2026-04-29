<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeOption;
use Symfony\Component\Uid\Uuid;

interface AttributeOptionRepositoryInterface
{
    public function findById(Uuid $id): ?AttributeOption;

    /**
     * @return list<AttributeOption>
     */
    public function findByAttribute(Attribute $attribute): array;

    public function save(AttributeOption $attributeOption): void;

    public function remove(AttributeOption $attributeOption): void;
}
