<?php

declare(strict_types=1);

namespace App\Identity\Domain\Rbac;

/**
 * Source of truth for the four built-in roles and their permissions.
 *
 * The matrix is a static, declarative listing — same shape that lands in
 * docs/rbac.md so engineers and the seeder read identical rules. Adding a
 * permission is a two-step change: extend RESOURCES/ACTIONS, then grant it
 * to the relevant role(s) below.
 *
 * Resources cover both entities that exist today (User, Role, Tenant) and
 * ones arriving with epics 0.3 (Object, ObjectType, Attribute, AttributeGroup,
 * Asset, Brand, Category) and 0.6 (Channel, Integration, ApiProfile). The
 * seeder is intentionally allowed to reference future resources — voters and
 * API surfaces will catch up to the matrix as they land.
 */
final class RbacMatrix
{
    public const string ROLE_SUPER_ADMIN = 'super_admin';
    public const string ROLE_CATALOG_MANAGER = 'catalog_manager';
    public const string ROLE_INTEGRATION_MANAGER = 'integration_manager';
    public const string ROLE_VIEWER = 'viewer';

    public const string ACTION_READ = 'read';
    public const string ACTION_WRITE = 'write';
    public const string ACTION_DELETE = 'delete';
    public const string ACTION_ADMIN = 'admin';

    /**
     * Resources known to the matrix. Order is alphabetic.
     */
    private const array RESOURCES = [
        'api_profile',
        'asset',
        'association',
        'attribute',
        'attribute_group',
        'backup',
        'brand',
        'category',
        'channel',
        'import_profile',
        'import_session',
        'integration',
        'object',
        'object_type',
        'role',
        'tenant',
        'user',
    ];

    private const array ACTIONS = [
        self::ACTION_READ,
        self::ACTION_WRITE,
        self::ACTION_DELETE,
        self::ACTION_ADMIN,
    ];

    /**
     * @return list<PermissionDefinition>
     */
    public static function permissions(): array
    {
        $permissions = [];
        foreach (self::RESOURCES as $resource) {
            foreach (self::ACTIONS as $action) {
                $permissions[] = new PermissionDefinition($resource, $action);
            }
        }

        return $permissions;
    }

    /**
     * @return list<RoleDefinition>
     */
    public static function roles(): array
    {
        return [
            new RoleDefinition(
                code: self::ROLE_SUPER_ADMIN,
                name: 'Super Admin',
                permissionCodes: self::allPermissionCodes(),
            ),
            new RoleDefinition(
                code: self::ROLE_CATALOG_MANAGER,
                name: 'Catalog Manager',
                permissionCodes: [
                    ...self::permissionsFor(
                        resources: ['object', 'object_type', 'attribute', 'attribute_group', 'association', 'category', 'asset', 'brand'],
                        actions: [self::ACTION_READ, self::ACTION_WRITE, self::ACTION_DELETE],
                    ),
                    // IMP-01/02 — Catalog Manager is the primary persona for
                    // self-service imports (Kasia, spec §2). Backup write is
                    // intentionally OUT — a cluster-wide pgBackRest snapshot
                    // is admin-territory (spec §7.8). Read on backup keeps
                    // the wizard's status polling working.
                    ...self::permissionsFor(
                        resources: ['import_session', 'import_profile'],
                        actions: [self::ACTION_READ, self::ACTION_WRITE, self::ACTION_DELETE],
                    ),
                    ...self::permissionsFor(
                        resources: ['backup'],
                        actions: [self::ACTION_READ],
                    ),
                ],
            ),
            new RoleDefinition(
                code: self::ROLE_INTEGRATION_MANAGER,
                name: 'Integration Manager',
                permissionCodes: [
                    ...self::permissionsFor(
                        resources: ['channel', 'integration', 'api_profile'],
                        actions: [self::ACTION_READ, self::ACTION_WRITE, self::ACTION_DELETE],
                    ),
                    // Read-only on catalog so an integrator can wire mappings
                    // without being able to mutate product data.
                    ...self::permissionsFor(
                        resources: ['object', 'object_type', 'attribute', 'category', 'brand', 'asset'],
                        actions: [self::ACTION_READ],
                    ),
                ],
            ),
            new RoleDefinition(
                code: self::ROLE_VIEWER,
                name: 'Viewer',
                permissionCodes: self::permissionsFor(
                    resources: self::RESOURCES,
                    actions: [self::ACTION_READ],
                ),
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private static function allPermissionCodes(): array
    {
        return array_map(static fn (PermissionDefinition $p): string => $p->code(), self::permissions());
    }

    /**
     * @param list<string> $resources
     * @param list<string> $actions
     *
     * @return list<string>
     */
    private static function permissionsFor(array $resources, array $actions): array
    {
        $codes = [];
        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                $codes[] = \sprintf('%s.%s', $resource, $action);
            }
        }

        return $codes;
    }
}
