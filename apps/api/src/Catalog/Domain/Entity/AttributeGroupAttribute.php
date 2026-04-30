<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

/**
 * Junction connecting an Attribute to an AttributeGroup (M:N) per ADR-012.
 *
 * One Attribute may live in many groups (e.g. `description` in both
 * "Marketing" and "SEO Defaults"); one group bundles many attributes.
 * Composite PK is the natural `(attribute_group_id, attribute_id)` pair.
 *
 * Coexists with the legacy 1:N path on `Attribute.group_id` during the
 * UI-08 migration. Data migration of the legacy column into this junction
 * is deferred to follow-up after #UI-08.5 — both relationships are valid
 * during the transition.
 *
 * `position` drives form rendering order inside the group. `isRequired-
 * InGroup` is *group-local* — different from `ObjectTypeAttribute.required-
 * ForCompleteness` (which feeds completeness scoring at object level).
 *
 * `visibleWhen` carries the optional conditional visibility rule shipped in
 * #UI-08.8: MVP supports a single `{field, operator: 'equals', value}`
 * payload; richer composites land in Faza 1+.
 *
 * No `tenant_id` column — tenant scope is inherited via the parent
 * AttributeGroup. Listed in `TenantAuditCommand::INFRA_TABLES` allowlist.
 */
class AttributeGroupAttribute
{
    private AttributeGroup $attributeGroup;
    private Attribute $attribute;
    private int $position = 0;
    private bool $isRequiredInGroup = false;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $visibleWhen = null;

    /**
     * @param array<string, mixed>|null $visibleWhen
     */
    public function __construct(
        AttributeGroup $attributeGroup,
        Attribute $attribute,
        int $position = 0,
        bool $isRequiredInGroup = false,
        ?array $visibleWhen = null,
    ) {
        $this->attributeGroup = $attributeGroup;
        $this->attribute = $attribute;
        $this->position = $position;
        $this->isRequiredInGroup = $isRequiredInGroup;
        $this->visibleWhen = $visibleWhen;
    }

    public function getAttributeGroup(): AttributeGroup
    {
        return $this->attributeGroup;
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function reorder(int $position): void
    {
        $this->position = $position;
    }

    public function isRequiredInGroup(): bool
    {
        return $this->isRequiredInGroup;
    }

    public function changeRequiredInGroup(bool $required): void
    {
        $this->isRequiredInGroup = $required;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getVisibleWhen(): ?array
    {
        return $this->visibleWhen;
    }

    /**
     * @param array<string, mixed>|null $visibleWhen
     */
    public function changeVisibleWhen(?array $visibleWhen): void
    {
        $this->visibleWhen = $visibleWhen;
    }
}
