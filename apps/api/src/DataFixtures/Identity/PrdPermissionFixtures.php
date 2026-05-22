<?php

declare(strict_types=1);

namespace App\DataFixtures\Identity;

use App\Identity\Domain\Entity\Permission;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P1-006 (#645) — seeds the ~50 atomic permissions from
 * PRD-PIM-rbac §3.2 macierz as a globally-immutable pool.
 *
 * Resource/action split logic:
 *   PRD codes are formatted as `{module}.{action}` or
 *   `{module}.{submodule}.{action}` (3-segment). The seeder splits on
 *   the LAST dot, so `settings.users.manage` becomes
 *   resource=`settings.users`, action=`manage` — preserving the namespace
 *   in resource while keeping the verb in action. The full PRD code
 *   stays as `code` (unique by separate constraint).
 *
 * Idempotency:
 *   Fixture checks `permissions.code` before INSERT — re-running it after
 *   a partial seed (or alongside the existing legacy `RbacMatrix` 76-row
 *   set) leaves the legacy rows untouched and adds only the missing PRD
 *   codes. The two sets coexist until Phase 6 retrofit (#714-#717)
 *   consolidates Voters onto PRD codes and drops the legacy.
 *
 * Co-existence with legacy `RbacMatrix`:
 *   The legacy 76-row set (resource ∈ ['object','asset',...] × action ∈
 *   ['read','write','delete','admin']) is what current Voters consume.
 *   The new PRD set lives alongside it and is the substrate Phase 2
 *   PermissionResolver / Phase 3 Voters will switch to. No data loss
 *   when the legacy is eventually dropped — the seeder is replayable.
 */
final class PrdPermissionFixtures extends Fixture
{
    /**
     * The ~50 atomic permissions from PRD-PIM-rbac §3.2 macierz.
     * Order: Cross-tenant → Products → Categories → Multimedia →
     * Modeling → Publications → Imports → Exports → Workflow →
     * Cmd+K agent → Settings → API tokens → Audit → Tenant lifecycle.
     *
     * @var list<string>
     */
    private const array PRD_PERMISSION_CODES = [
        // Cross-tenant (Super Admin only)
        'platform.tenants.list',
        'platform.tenants.manage',
        'platform.audit.view_all',
        'platform.break_glass_recovery',

        // Produkty
        'products.view',
        'products.add',
        'products.edit',
        'products.delete',
        'products.bulk_operations',
        'products.approve_pending_changes',

        // Kategorie
        'categories.view',
        'categories.add_edit',
        'categories.delete',

        // Multimedia (DAM)
        'multimedia.view',
        'multimedia.add_edit_own',
        'multimedia.add_edit_any',
        'multimedia.delete',

        // Modelowanie
        'modeling.view',
        'modeling.attributes.add_edit',
        'modeling.attribute_groups.add_edit',
        'modeling.object_types.add',
        'modeling.delete_custom',
        'modeling.approve_schema_ops',
        'modeling.auto_grant_new_object_types',

        // Publikacje
        'publications.view',
        'publications.publish_unpublish',

        // Imports
        'imports.view_own',
        'imports.view_all',
        'imports.run',

        // Exports
        'exports.view_own',
        'exports.view_all',
        'exports.run',

        // Workflow
        'workflow.view',
        'workflow.approve_reject',
        'workflow.edit_any_state',

        // Cmd+K agent
        'agent.schema_ops',
        'agent.bulk_actions',
        'agent.approve_pending',

        // Settings
        'settings.users.manage',
        'settings.roles.manage',
        'settings.tenant.manage',
        'settings.locales.manage',
        'settings.billing.manage',
        'settings.integrations.manage',
        'settings.integration_secrets.read',

        // API tokens
        'api_tokens.own.crud',
        'api_tokens.all.view_revoke',

        // Audit
        'audit.view_own',
        'audit.view_cross_user',

        // Tenant lifecycle
        'tenant.delete',
    ];

    public function load(ObjectManager $manager): void
    {
        $repo = $manager->getRepository(Permission::class);
        $created = 0;

        foreach (self::PRD_PERMISSION_CODES as $code) {
            if (null !== $repo->findOneBy(['code' => $code])) {
                continue; // idempotent — already seeded
            }

            [$resource, $action] = self::splitCode($code);
            $manager->persist(new Permission(
                resource: $resource,
                action: $action,
                code: $code,
                id: Uuid::v7(),
            ));
            ++$created;
        }

        if ($created > 0) {
            $manager->flush();
        }
    }

    /**
     * Split a PRD permission code into (resource, action) on the LAST dot.
     *
     * @return array{0: string, 1: string}
     */
    private static function splitCode(string $code): array
    {
        $lastDot = strrpos($code, '.');
        if (false === $lastDot) {
            return ['', $code];
        }

        return [substr($code, 0, $lastDot), substr($code, $lastDot + 1)];
    }
}
