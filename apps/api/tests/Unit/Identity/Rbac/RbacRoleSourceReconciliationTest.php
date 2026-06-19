<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Rbac;

use App\Identity\Domain\Rbac\PrdRoleTemplates;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Identity\Domain\Rbac\RoleDefinition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * AUD-065 (W3-5.2) — make the RBAC role drift AUDITABLE from one place.
 *
 * Two seeders write roles into the DB:
 *   - {@see \App\Identity\Application\RbacSeeder} reads {@see RbacMatrix::roles()}
 *     (global built-in roles, tenant_id NULL).
 *   - {@see \App\Identity\Application\SeedTenantPrdRolesService} reads
 *     {@see PrdRoleTemplates::tenantRolePermissions()} (per-tenant PRD §3.2
 *     roles) plus the global `super_admin` from {@see PrdRoleTemplates}.
 *
 * Before AUD-065 the two sources drifted silently: RbacMatrix declares ~5
 * roles, the PRD templates 10, and nothing failed when a role existed in one
 * source but not the canonical PRD §3.2 set. This meta-test asserts that
 * EVERY role code reachable by either seeder is represented in the canonical
 * source of truth (PRD-PIM-rbac §3.2), so adding a role to a seeder without a
 * matching canonical entry fails the build.
 *
 * It deliberately verifies role *coverage* only — it does NOT assert grant
 * contents (those belong to RbacSeederTest / RbacVotersTest), so it changes
 * zero permissions.
 */
final class RbacRoleSourceReconciliationTest extends TestCase
{
    /**
     * Canonical role codes from PRD-PIM-rbac §3.2 (the macierz uprawnień):
     * 1 platform-level (`super_admin`) + 9 tenant-level (Owner, Admin,
     * Catalog Mgr, Marketing, Modeler, Integ. Mgr, Channel Mgr, Approver,
     * Viewer) + the AUD-003 `platform_operator` (the only holder of the
     * cross-tenant `platform.*` grants; PRD §3.2 "Super Admin only" block).
     *
     * This list is the single audit surface: every seeded role must appear
     * here, and every entry here is justified by PRD §3.2.
     *
     * @var list<string>
     */
    private const array CANONICAL_ROLE_CODES = [
        // Platform-level
        'super_admin',
        'platform_operator',
        // Tenant-level (PRD §3.2 matrix columns, left-to-right after Super Admin)
        'tenant_owner',
        'admin',
        'catalog_manager',
        'marketing',
        'modeler',
        'integration_manager',
        'channel_manager',
        'approver',
        'viewer',
    ];

    #[Test]
    public function everyGlobalSeededRoleIsRepresentedInTheCanonicalSource(): void
    {
        $globalRoleCodes = array_map(
            static fn (RoleDefinition $definition): string => $definition->code,
            RbacMatrix::roles(),
        );

        foreach ($globalRoleCodes as $code) {
            self::assertContains(
                $code,
                self::CANONICAL_ROLE_CODES,
                \sprintf(
                    'RbacMatrix seeds global role "%s" but it is absent from the canonical '
                    .'PRD §3.2 role set. Add it to CANONICAL_ROLE_CODES (and PRD §3.2) or remove it from RbacMatrix.',
                    $code,
                ),
            );
        }
    }

    #[Test]
    public function everyPerTenantSeededRoleIsRepresentedInTheCanonicalSource(): void
    {
        $tenantRoleCodes = array_keys(PrdRoleTemplates::tenantRolePermissions());

        foreach ($tenantRoleCodes as $code) {
            self::assertContains(
                $code,
                self::CANONICAL_ROLE_CODES,
                \sprintf(
                    'SeedTenantPrdRolesService seeds per-tenant role "%s" but it is absent from '
                    .'the canonical PRD §3.2 role set. Add it to CANONICAL_ROLE_CODES (and PRD §3.2).',
                    $code,
                ),
            );
        }
    }

    #[Test]
    public function tenantRoleNamesAndPermissionsCoverTheSameRoleCodes(): void
    {
        // A name without a permission set (or vice versa) is the exact kind of
        // half-defined role that produces a drift; assert the two PrdRoleTemplates
        // maps stay in lockstep.
        $nameCodes = array_keys(PrdRoleTemplates::tenantRoleNames());
        $permissionCodes = array_keys(PrdRoleTemplates::tenantRolePermissions());

        sort($nameCodes);
        sort($permissionCodes);

        self::assertSame(
            $nameCodes,
            $permissionCodes,
            'PrdRoleTemplates::tenantRoleNames() and tenantRolePermissions() must define the same role codes.',
        );
    }

    #[Test]
    public function canonicalSourceHasNoUnusedRoleCodes(): void
    {
        // Guard the reverse direction: a canonical entry that no seeder emits
        // is dead documentation. `tenant_owner`/`admin`/`marketing`/`modeler`/
        // `channel_manager`/`approver` come from PrdRoleTemplates; the rest
        // (super_admin / platform_operator / catalog_manager / integration_manager
        // / viewer) come from RbacMatrix and/or PrdRoleTemplates.
        $seededCodes = array_unique([
            ...array_map(static fn (RoleDefinition $d): string => $d->code, RbacMatrix::roles()),
            ...array_keys(PrdRoleTemplates::tenantRolePermissions()),
            'super_admin', // PrdRoleTemplates::superAdminPermissions() seeds this globally
        ]);

        foreach (self::CANONICAL_ROLE_CODES as $code) {
            self::assertContains(
                $code,
                $seededCodes,
                \sprintf(
                    'Canonical role "%s" is declared in PRD §3.2 but no seeder emits it. '
                    .'Remove it from CANONICAL_ROLE_CODES or wire a seeder.',
                    $code,
                ),
            );
        }
    }
}
