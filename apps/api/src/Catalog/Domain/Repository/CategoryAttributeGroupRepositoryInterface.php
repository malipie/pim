<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Repository;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\CategoryAttributeGroup;
use App\Catalog\Domain\Entity\ObjectType;

/**
 * VIEW-04 (#408) — repository contract for the
 * `category × target_object_type × attribute_group` junction.
 *
 * The junction has a composite PK so the standard `find()` shape
 * does not apply — call sites need it sliced by category + target
 * type (the natural axis of the modelling Detail panel) or as a
 * single tuple lookup.
 */
interface CategoryAttributeGroupRepositoryInterface
{
    /**
     * Lookup a single junction row. Returns `null` if the (category,
     * target type, group) combination is not declared.
     */
    public function findOne(
        CatalogObject $category,
        ObjectType $targetObjectType,
        AttributeGroup $attributeGroup,
    ): ?CategoryAttributeGroup;

    /**
     * Declared groups on a single category for a given target ObjectType,
     * ordered by `position` ASC then `attribute_group.code` ASC for a
     * deterministic UI rendering. Excludes inherited groups (those live
     * on ancestor categories) — caller composes the layered list.
     *
     * @return list<CategoryAttributeGroup>
     */
    public function findByCategoryAndTarget(
        CatalogObject $category,
        ObjectType $targetObjectType,
    ): array;

    /**
     * Maximum `position` already declared on this (category, target)
     * pair, or `null` when none. Used by the controller to allocate
     * the next slot on POST without a race with concurrent declares
     * (the operation is admin-only and serialised at request boundary).
     */
    public function maxPosition(
        CatalogObject $category,
        ObjectType $targetObjectType,
    ): ?int;

    public function save(CategoryAttributeGroup $junction): void;

    public function remove(CategoryAttributeGroup $junction): void;
}
