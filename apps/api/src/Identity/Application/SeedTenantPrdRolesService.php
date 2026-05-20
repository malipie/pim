<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Entity\Role;
use App\Identity\Domain\Rbac\PrdRoleTemplates;
use App\Identity\Domain\Repository\PermissionRepositoryInterface;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * RBAC-P1-007 (#646) — seeds the 8 PRD-PIM-rbac §3.2 tenant-level role
 * templates (tenant_owner / admin / catalog_manager / marketing /
 * modeler / integration_manager / channel_manager / approver / viewer)
 * into a target tenant.
 *
 * Extracted from `SeedTenantPrdRolesCommand` so the same logic can be
 * called from:
 *   - the CLI command (operator-driven onboarding),
 *   - dev fixtures (`AppFixtures`), so a `doctrine:fixtures:load` boot
 *     yields a full PRD role graph + a tenant_owner-assigned admin
 *     without an extra manual command.
 *
 * Idempotent: existing rows matched by (tenant_id, code) are kept;
 * the service only fills the gap. Permission attachments are diffed —
 * a role that gains a new permission via a PRD update picks it up
 * on the next call.
 */
final readonly class SeedTenantPrdRolesService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoleRepositoryInterface $roleRepository,
        private PermissionRepositoryInterface $permissionRepository,
    ) {
    }

    /**
     * @return array{created: int, updated: int, missing_permissions: list<string>}
     */
    public function seed(Tenant $tenant): array
    {
        $rolePermissions = PrdRoleTemplates::tenantRolePermissions();
        $roleNames = PrdRoleTemplates::tenantRoleNames();

        $created = 0;
        $updated = 0;
        $missingPermissions = [];

        foreach ($rolePermissions as $code => $permissionCodes) {
            $role = $this->roleRepository->findByCode($code, $tenant);
            if (null === $role) {
                $role = new Role(
                    code: $code,
                    name: $roleNames[$code],
                    tenant: $tenant,
                );
                $this->em->persist($role);
                ++$created;
            } else {
                ++$updated;
            }

            $existingPermissionCodes = [];
            foreach ($role->getPermissions() as $existing) {
                $existingPermissionCodes[] = $existing->getCode();
            }

            foreach ($permissionCodes as $permissionCode) {
                if (\in_array($permissionCode, $existingPermissionCodes, true)) {
                    continue;
                }
                $permission = $this->permissionRepository->findByCode($permissionCode);
                if (null === $permission) {
                    $missingPermissions[] = $permissionCode;
                    continue;
                }
                $role->getPermissions()->add($permission);
            }
        }

        $this->em->flush();

        return [
            'created' => $created,
            'updated' => $updated,
            'missing_permissions' => array_values(array_unique($missingPermissions)),
        ];
    }
}
