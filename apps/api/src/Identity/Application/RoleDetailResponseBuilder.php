<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Entity\Role;
use DateTimeInterface;

/**
 * RBAC-P5-006 (#696) — full role projection consumed by the
 * custom-role builder editor.
 *
 * Unlike {@see RoleListResponseBuilder}, which compresses each role
 * into a single list row, this builder exposes the role's permission
 * codes so the matrix grid in the FE can pre-check the granted cells.
 */
final class RoleDetailResponseBuilder
{
    public const string TYPE_SYSTEM = 'system';
    public const string TYPE_CUSTOM = 'custom';

    /**
     * @return array{
     *     id: string,
     *     code: string,
     *     name: string,
     *     type: string,
     *     is_built_in: bool,
     *     tenant_id: ?string,
     *     permission_codes: list<string>,
     *     created_at: string
     * }
     */
    public function buildOne(Role $role): array
    {
        $type = $role->isGlobal() ? self::TYPE_SYSTEM : self::TYPE_CUSTOM;
        $permissionCodes = [];
        foreach ($role->getPermissions() as $permission) {
            $permissionCodes[] = $permission->getCode();
        }
        sort($permissionCodes);

        return [
            'id' => $role->getId()->toRfc4122(),
            'code' => $role->getCode(),
            'name' => $role->getName(),
            'type' => $type,
            'is_built_in' => $role->isGlobal(),
            'tenant_id' => $role->getTenant()?->getId()->toRfc4122(),
            'permission_codes' => $permissionCodes,
            'created_at' => $role->getCreatedAt()->format(DateTimeInterface::ATOM),
        ];
    }
}
