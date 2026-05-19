<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security\Prd;

use App\Catalog\Domain\Entity\Attribute;
use App\Identity\Infrastructure\Security\AbstractPrdVoter;

/**
 * RBAC-P3-004 (#667) — per-Attribute authorization aligned with the
 * PRD §3.2 Modeling row (`modeling.view`, `modeling.attributes.add_edit`).
 *
 * `delete` collapses onto the same `add_edit` code: the macierz treats
 * full attribute CRUD as one Modeler-owned action. There is no analogue
 * to ObjectType's built-in protection here — every attribute, including
 * the four `is_system=true` rows (`created_at`, `updated_at`, `created_by`,
 * `updated_by`), is in scope; system-row protection belongs to the
 * controller / migration boundary.
 *
 * Sibling: {@see ObjectTypeVoter} for the kind dimension,
 * {@see AttributeGroupVoter} for groupings.
 */
final class AttributeVoter extends AbstractPrdVoter
{
    /**
     * @return array<string, string>
     */
    protected function permissionMap(): array
    {
        return [
            'view' => 'modeling.view',
            'add_edit' => 'modeling.attributes.add_edit',
            'delete' => 'modeling.attributes.add_edit',
        ];
    }

    protected function subjectClass(): string
    {
        return Attribute::class;
    }
}
