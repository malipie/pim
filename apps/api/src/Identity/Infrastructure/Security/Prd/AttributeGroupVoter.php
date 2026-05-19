<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security\Prd;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Identity\Infrastructure\Security\AbstractPrdVoter;

/**
 * RBAC-P3-004 (#667) — per-AttributeGroup authorization aligned with the
 * PRD §3.2 Modeling row (`modeling.view`, `modeling.attribute_groups.add_edit`).
 *
 * AttributeGroup has no separate `delete` code in the macierz — its
 * lifecycle is bundled into `add_edit`. There is also no delete action
 * exposed by the voter; the macierz simply does not surface group
 * deletion as a distinct permission. The controller relies on cascade
 * rules from the database (groups detached on attribute migration / type
 * change).
 */
final class AttributeGroupVoter extends AbstractPrdVoter
{
    /**
     * @return array<string, string>
     */
    protected function permissionMap(): array
    {
        return [
            'view' => 'modeling.view',
            'add_edit' => 'modeling.attribute_groups.add_edit',
        ];
    }

    protected function subjectClass(): string
    {
        return AttributeGroup::class;
    }
}
