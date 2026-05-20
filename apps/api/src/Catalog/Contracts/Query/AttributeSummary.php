<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Query;

use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P5-007 (#697) — cross-BC read projection of
 * {@see \App\Catalog\Domain\Entity\Attribute} for the Identity bundle.
 *
 * Identity's role attribute-permissions tab needs to enumerate the
 * tenant's attributes (grouped by AttributeGroup) without coupling
 * to the full Catalog domain model. This DTO carries only what the
 * Settings UI projects to the operator: id + code + localised label
 * + type tag + flags + group metadata.
 */
final readonly class AttributeSummary
{
    /**
     * @param array<string, string> $label
     * @param array<string, string> $groupLabel
     */
    public function __construct(
        public Uuid $id,
        public Uuid $tenantId,
        public string $code,
        public array $label,
        public string $type,
        public bool $isLocalizable,
        public bool $isRequired,
        public bool $isSystem,
        public ?Uuid $groupId,
        public ?string $groupCode,
        public array $groupLabel,
        public int $groupPosition,
    ) {
    }
}
