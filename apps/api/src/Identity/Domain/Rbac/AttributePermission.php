<?php

declare(strict_types=1);

namespace App\Identity\Domain\Rbac;

/**
 * RBAC-P3-008 (#671) — 3-state attribute permission per PRD §3.5.
 *
 * Each entry on `role_attribute_permissions(role_id, attribute_id)` /
 * `role_attribute_group_permissions(role_id, attribute_group_id)` carries
 * one of three values, and `roles.default_attribute_permission` falls
 * back to one of the three when no override matches.
 *
 *   - `restricted` — attribute is hidden from the response and rejects
 *                    edits (full removal in the serializer + 403 on PATCH),
 *   - `view`       — value visible, edits rejected (`{value, editable:
 *                    false, reason: "view_only"}` shape per §3.5),
 *   - `edit`       — full read + write.
 *
 * The {@see rank()} method orders the values from least- to most-permissive
 * so {@see \App\Identity\Application\Policy\AttributePermissionPolicy} can
 * merge multiple roles by taking the maximum.
 */
enum AttributePermission: string
{
    case Restricted = 'restricted';
    case View = 'view';
    case Edit = 'edit';

    public function rank(): int
    {
        return match ($this) {
            self::Restricted => 0,
            self::View => 1,
            self::Edit => 2,
        };
    }

    public function canView(): bool
    {
        return self::Restricted !== $this;
    }

    public function canEdit(): bool
    {
        return self::Edit === $this;
    }
}
