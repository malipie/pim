<?php

declare(strict_types=1);

namespace App\Identity\Application\Policy;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\AttributePermission;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P3-008 (#671) — 3-state attribute permission resolution per
 * PRD §3.5.
 *
 * **Resolution chain (per role, then merged most-permissive):**
 *
 *   0. **Broad gate first** (decyzja designerska A) — the caller must
 *      hold either `products.view` or `products.edit` in the macierz.
 *      If neither, every per-attribute grant is inactive and the policy
 *      returns `Restricted` immediately.
 *
 *   1. **Per-attribute override** —
 *      `role_attribute_permissions(role_id, attribute_id)`. First entry
 *      wins for that role.
 *
 *   2. **Per-group override** —
 *      `role_attribute_group_permissions(role_id, attribute_group_id)`.
 *      The attribute can belong to multiple groups via
 *      `attribute_group_attributes`; the most-permissive group entry
 *      among them is taken.
 *
 *   3. **Role default** — `roles.default_attribute_permission` falls
 *      back to one of the three values (defaults to `edit` per schema).
 *
 * **Multi-role merging:** the user can carry multiple roles (both via
 * `user_role_assignments` and the legacy `user_roles` M2M). The policy
 * resolves per role and returns `max(rank)` — most-permissive role
 * wins. This matches PermissionResolver's union semantics for broad
 * permissions.
 *
 * **Tenant scope:** roles are tenant-scoped through `roles.tenant_id`;
 * `user_roles` / `user_role_assignments` carry the link from a
 * tenant-scoped user to a tenant-scoped role. No additional tenant
 * compare is needed here — the role IDs we collect are already inside
 * the caller's tenant.
 *
 * **Cache:** not wired in this ticket. The three SELECTs are indexed
 * (primary key on the junction tables, FK index on roles.id), so a
 * single resolve runs in O(roles) cheap lookups. Cache + invalidation
 * listeners land in a follow-up once profiling shows the call path is
 * hot enough to amortise the invalidation complexity (Phase 6 #720
 * benchmark ticket).
 *
 * **`integration_visible` flag:** independent semantics, lives in the
 * serializer (RBAC-P3-012 #675). This policy resolves only the per-role
 * 3-state values — the serializer composes them with the integration
 * flag for the final response shape.
 */
final readonly class AttributePermissionPolicy
{
    public function __construct(
        private Connection $connection,
        private PermissionResolverInterface $resolver,
    ) {
    }

    public function resolvePermission(User $user, Uuid $attributeId): AttributePermission
    {
        // Step 0 — broad gate first (PRD §3.5 decyzja designerska A).
        $permissions = $this->resolver->resolve($user);
        if (!$permissions->has('products.view') && !$permissions->has('products.edit')) {
            return AttributePermission::Restricted;
        }

        $roleIds = $this->collectRoleIds($user->getId());
        if ([] === $roleIds) {
            return AttributePermission::Restricted;
        }

        $best = AttributePermission::Restricted;
        foreach ($roleIds as $roleId) {
            $resolved = $this->resolveForRole($roleId, $attributeId);
            if ($resolved->rank() > $best->rank()) {
                $best = $resolved;
            }
            if (AttributePermission::Edit === $best) {
                // Most-permissive already reached — short-circuit. Saves
                // the remaining per-role queries on Owner/Admin users
                // that carry several roles.
                return $best;
            }
        }

        return $best;
    }

    public function canEditAttribute(User $user, Uuid $attributeId): bool
    {
        return $this->resolvePermission($user, $attributeId)->canEdit();
    }

    public function canViewAttribute(User $user, Uuid $attributeId): bool
    {
        return $this->resolvePermission($user, $attributeId)->canView();
    }

    /**
     * @return list<string> role UUIDs (RFC4122 strings) accessible to the
     *                      user via either `user_role_assignments` or the
     *                      legacy `user_roles` M2M, deduplicated
     */
    private function collectRoleIds(Uuid $userId): array
    {
        $sql = <<<'SQL'
            SELECT DISTINCT role_id::text AS role_id
              FROM user_role_assignments
             WHERE user_id = :user_id
            UNION
            SELECT DISTINCT role_id::text AS role_id
              FROM user_roles
             WHERE user_id = :user_id
            SQL;

        /** @var list<array{role_id: string}> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, ['user_id' => $userId->toRfc4122()]);

        return array_map(static fn (array $row): string => $row['role_id'], $rows);
    }

    private function resolveForRole(string $roleId, Uuid $attributeId): AttributePermission
    {
        // 1. Per-attribute override — direct lookup, primary-key indexed.
        $perAttr = $this->connection->fetchOne(
            'SELECT permission FROM role_attribute_permissions WHERE role_id = :role_id AND attribute_id = :attribute_id',
            ['role_id' => $roleId, 'attribute_id' => $attributeId->toRfc4122()],
        );
        if (false !== $perAttr && \is_string($perAttr)) {
            return AttributePermission::from($perAttr);
        }

        // 2. Per-group override — attribute may belong to multiple groups;
        //    take the most-permissive group grant for this role.
        $perGroup = $this->connection->fetchOne(
            <<<'SQL'
                SELECT rgp.permission
                  FROM role_attribute_group_permissions rgp
                  JOIN attribute_group_attributes aga ON aga.attribute_group_id = rgp.attribute_group_id
                 WHERE rgp.role_id = :role_id
                   AND aga.attribute_id = :attribute_id
                 ORDER BY CASE rgp.permission
                              WHEN 'edit' THEN 2
                              WHEN 'view' THEN 1
                              ELSE 0
                          END DESC
                 LIMIT 1
                SQL,
            ['role_id' => $roleId, 'attribute_id' => $attributeId->toRfc4122()],
        );
        if (false !== $perGroup && \is_string($perGroup)) {
            return AttributePermission::from($perGroup);
        }

        // 3. Role default — column defaults to 'edit' for tenant-level
        //    roles, 'view' for viewer, 'restricted' for explicit setups.
        $default = $this->connection->fetchOne(
            'SELECT default_attribute_permission FROM roles WHERE id = :role_id',
            ['role_id' => $roleId],
        );
        if (false !== $default && \is_string($default)) {
            return AttributePermission::from($default);
        }

        return AttributePermission::Restricted;
    }
}
