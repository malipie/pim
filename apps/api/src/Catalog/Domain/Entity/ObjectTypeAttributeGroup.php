<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use InvalidArgumentException;

/**
 * Junction connecting an AttributeGroup to an ObjectType (M:N) per ADR-012.
 *
 * Represents *global* groups attached to an ObjectType — every object of
 * that type sees these groups in its form schema, regardless of category.
 * E.g. `Audit` (auto-attached, system-managed) and `Identification` are
 * always wired here. User-defined groups like "Marketing" attach here too.
 *
 * Composite PK is the `(object_type_id, attribute_group_id)` pair.
 *
 * For category-driven groups (group declared on a specific category and
 * inherited by descendants), see `CategoryAttributeGroup`.
 *
 * No `tenant_id` column — tenant scope is inherited via the parent
 * ObjectType. Listed in `TenantAuditCommand::INFRA_TABLES` allowlist.
 */
class ObjectTypeAttributeGroup
{
    public const string DISPLAY_MODE_TAB = 'tab';
    public const string DISPLAY_MODE_STACKED = 'stacked';

    /** @var list<string> */
    public const array DISPLAY_MODES = [self::DISPLAY_MODE_TAB, self::DISPLAY_MODE_STACKED];

    private ObjectType $objectType;
    private AttributeGroup $attributeGroup;
    private int $position = 0;
    private string $displayMode = self::DISPLAY_MODE_TAB;

    public function __construct(
        ObjectType $objectType,
        AttributeGroup $attributeGroup,
        int $position = 0,
        string $displayMode = self::DISPLAY_MODE_TAB,
    ) {
        $this->objectType = $objectType;
        $this->attributeGroup = $attributeGroup;
        $this->position = $position;
        $this->assertDisplayMode($displayMode);
        $this->displayMode = $displayMode;
    }

    public function getObjectType(): ObjectType
    {
        return $this->objectType;
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

    public function getDisplayMode(): string
    {
        return $this->displayMode;
    }

    public function changeDisplayMode(string $displayMode): void
    {
        $this->assertDisplayMode($displayMode);
        $this->displayMode = $displayMode;
    }

    private function assertDisplayMode(string $value): void
    {
        if (!\in_array($value, self::DISPLAY_MODES, true)) {
            throw new InvalidArgumentException(\sprintf(
                'Invalid display_mode "%s" — expected one of: %s.',
                $value,
                implode(', ', self::DISPLAY_MODES),
            ));
        }
    }
}
