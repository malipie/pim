<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use Symfony\Component\Uid\Uuid;

/**
 * Junction declaring an AttributeGroup on a category for a specific
 * ObjectType (M:N×ObjectType) per ADR-012.
 *
 * `categoryObjectId` points at the `objects` row that *is* the category
 * (kind='category' per ADR-009). `targetObjectTypeId` is which kind of
 * objects (e.g. `Service`, `Product`) placed under this category — and
 * its descendants in the ltree — will inherit this group.
 *
 * Walked by `EffectiveAttributeGroupResolver` (#UI-08.4) from root to
 * leaf to compute the effective group list for a (object, category_path)
 * pair. Groups declared higher in the tree win on conflict; child
 * categories layer additional groups on top.
 *
 * Composite PK is `(category_object_id, target_object_type_id, attribute_-
 * group_id)` — the same group can be declared on the same category for
 * different target ObjectTypes (e.g. "Marketing assets" group declared
 * on category `Promotions` for both `Product` and `Bundle`).
 *
 * No `tenant_id` column — tenant scope is inherited via the parent
 * category (which is a tenant-scoped catalog object). Listed in
 * `TenantAuditCommand::INFRA_TABLES` allowlist.
 */
class CategoryAttributeGroup
{
    private Uuid $categoryObjectId;
    private ObjectType $targetObjectType;
    private AttributeGroup $attributeGroup;
    private int $position = 0;

    public function __construct(
        Uuid $categoryObjectId,
        ObjectType $targetObjectType,
        AttributeGroup $attributeGroup,
        int $position = 0,
    ) {
        $this->categoryObjectId = $categoryObjectId;
        $this->targetObjectType = $targetObjectType;
        $this->attributeGroup = $attributeGroup;
        $this->position = $position;
    }

    public function getCategoryObjectId(): Uuid
    {
        return $this->categoryObjectId;
    }

    public function getTargetObjectType(): ObjectType
    {
        return $this->targetObjectType;
    }

    public function getAttributeGroup(): AttributeGroup
    {
        return $this->attributeGroup;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function reorder(int $position): void
    {
        $this->position = $position;
    }
}
