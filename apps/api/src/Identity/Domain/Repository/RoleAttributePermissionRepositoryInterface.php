<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\RoleAttributePermission;
use Symfony\Component\Uid\Uuid;

interface RoleAttributePermissionRepositoryInterface
{
    public function findById(Uuid $id): ?RoleAttributePermission;

    public function findByRoleAndAttribute(Uuid $roleId, Uuid $attributeId): ?RoleAttributePermission;

    /**
     * @return list<RoleAttributePermission>
     */
    public function findByRole(Uuid $roleId): array;

    public function save(RoleAttributePermission $entity): void;

    public function remove(RoleAttributePermission $entity): void;

    /**
     * Bulk-replace the attribute-permission set on a role. Deletes any
     * row for `roleId` whose `attributeId` is not in the new set, and
     * upserts the new rows. Single transaction so the resolver never
     * sees a half-applied state.
     *
     * @param list<RoleAttributePermission> $next replacement set; each
     *                                            entity's `roleId` must
     *                                            equal the `$roleId`
     *                                            argument
     */
    public function replaceForRole(Uuid $roleId, array $next): void;
}
