<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\UpdateAttributeGroupAttribute;

use Symfony\Component\Uid\Uuid;

/**
 * UI-08.8 (#263) — update the metadata of an `AttributeGroupAttribute`
 * junction row: position, isRequiredInGroup, visibleWhen rule.
 *
 * Membership itself (attaching/detaching attributes from groups) is
 * handled by future commands `AttachAttributeToGroup` /
 * `DetachAttributeFromGroup` — those land alongside the drag-drop UI in
 * #UI-08.13.
 */
final readonly class UpdateAttributeGroupAttributeCommand
{
    /**
     * @param array<string, mixed>|null $visibleWhen rule payload, or null to clear
     */
    public function __construct(
        public Uuid $attributeGroupId,
        public Uuid $attributeId,
        public ?int $position = null,
        public ?bool $isRequiredInGroup = null,
        public ?array $visibleWhen = null,
        public bool $clearVisibleWhen = false,
    ) {
    }
}
