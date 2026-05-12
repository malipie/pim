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

    /**
     * Bulk variant of {@see findByAttribute}. Returns every option whose
     * parent Attribute is in `$attributes`, sorted by attribute then by
     * position. Used by the product detail / variants tab eager loader so
     * select-like attributes ship their options in one round-trip.
     *
     * @param list<Attribute> $attributes
     *
     * @return list<AttributeOption>
     */
    public function findByAttributes(array $attributes): array;

    public function save(AttributeOption $attributeOption): void;

    public function remove(AttributeOption $attributeOption): void;
}
