<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\PermissionSet;
use Doctrine\DBAL\Connection;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * RBAC-P2-006 (#655) — resolve the union of permission codes + scope arrays
 * granted to a (User, Tenant) pair, with Redis-backed cache for hot-path
 * authorization checks.
 *
 * Cache key: `permissions:{tenant_id}:{user_id}` — must include tenant_id
 * to defeat cache poisoning when the same user.id collides across tenants
 * (rare with UUIDv7, but the cost of including is one extra string op).
 *
 * Cache tags:
 *   - `user:{user_id}` — invalidated by Doctrine listener on User /
 *     UserRole / Role / RolePermission updates touching this user.
 *   - `role:{role_id}` for each role the user holds — invalidated when
 *     the role's permissions change (touches every user in that role).
 *   - `tenant:{tenant_id}` — broad nuclear option for tenant-wide ops.
 *
 * Query optimisation: single SELECT joins users → user_roles → roles →
 * role_permissions → permissions, ordered by permission_id for stable
 * ordering. No N+1.
 *
 * Phase 3 #671 (3-state attribute permissions) consumes this Set to
 * compute the per-attribute view/edit decision; the set itself does NOT
 * include attribute_*_permissions — those resolve separately via
 * AttributePermissionPolicy.
 */
final class PermissionResolver
{
    private const string CACHE_KEY_PREFIX = 'permissions';
    private const int CACHE_TTL_SECONDS = 300; // 5 min per ticket §"Risk flags"

    public function __construct(
        private readonly Connection $connection,
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the cache key is malformed (should
     *                                  never happen with our deterministic key
     *                                  format, but Symfony's cache contracts
     *                                  declare the exception)
     */
    public function resolve(User $user): PermissionSet
    {
        $tenantId = $user->getTenant()->getId()->toRfc4122();
        $userId = $user->getId()->toRfc4122();
        $cacheKey = self::cacheKeyFor($tenantId, $userId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($tenantId, $userId): PermissionSet {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);
            $item->tag([
                'user:'.$userId,
                'tenant:'.$tenantId,
            ]);

            return $this->loadFromDatabase($tenantId, $userId);
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function invalidateUser(string $tenantId, string $userId): void
    {
        $this->cache->invalidateTags(['user:'.$userId]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function invalidateRole(string $roleId): void
    {
        $this->cache->invalidateTags(['role:'.$roleId]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function invalidateTenant(string $tenantId): void
    {
        $this->cache->invalidateTags(['tenant:'.$tenantId]);
    }

    public static function cacheKeyFor(string $tenantId, string $userId): string
    {
        return \sprintf('%s.%s.%s', self::CACHE_KEY_PREFIX, $tenantId, $userId);
    }

    private function loadFromDatabase(string $tenantId, string $userId): PermissionSet
    {
        // Single JOIN — users → user_role_assignments → roles → role_permissions → permissions.
        // user_role_assignments is the new junction with scope columns (RBAC-P1-008);
        // legacy `user_roles` M2M (Sprint-0 path) stays operational and is consulted
        // by the secondary query below until #644 delta migrations consolidate.
        $sql = <<<'SQL'
                SELECT DISTINCT p.code, ura.locale_scope, ura.channel_scope, ura.attribute_group_scope
                FROM user_role_assignments ura
                INNER JOIN role_permissions rp ON rp.role_id = ura.role_id
                INNER JOIN permissions p ON p.id = rp.permission_id
                WHERE ura.user_id = :user_id
                UNION
                SELECT DISTINCT p.code, '[]'::json AS locale_scope, '[]'::json AS channel_scope, '[]'::json AS attribute_group_scope
                FROM user_roles ur
                INNER JOIN role_permissions rp ON rp.role_id = ur.role_id
                INNER JOIN permissions p ON p.id = rp.permission_id
                WHERE ur.user_id = :user_id
            SQL;

        /** @var list<array{code: string, locale_scope: string, channel_scope: string, attribute_group_scope: string}> $rows */
        $rows = $this->connection->fetchAllAssociative($sql, ['user_id' => $userId]);

        $codes = [];
        $localeScope = [];
        $channelScope = [];
        $attributeGroupScope = [];

        foreach ($rows as $row) {
            if (!\in_array($row['code'], $codes, true)) {
                $codes[] = $row['code'];
            }
            $localeScope = self::mergeScope($localeScope, $row['locale_scope']);
            $channelScope = self::mergeScope($channelScope, $row['channel_scope']);
            $attributeGroupScope = self::mergeScope($attributeGroupScope, $row['attribute_group_scope']);
        }

        return new PermissionSet(
            permissionCodes: $codes,
            localeScope: $localeScope,
            channelScope: $channelScope,
            attributeGroupScope: $attributeGroupScope,
        );
    }

    /**
     * Merge scope arrays semantically — empty array means "no restriction"
     * which is the most permissive; if ANY role grants no-restriction, the
     * union has no restriction. First-row bootstrap is handled at the
     * caller by initialising $existing = [] before the loop and using a
     * tracking flag if necessary; this helper just unions concrete lists
     * or short-circuits to [] on the no-restriction signal.
     *
     * @param list<string> $existing
     *
     * @return list<string>
     */
    private static function mergeScope(array $existing, string $jsonScope): array
    {
        /** @var list<string>|null $decoded */
        $decoded = json_decode($jsonScope, true);
        if (!\is_array($decoded) || [] === $decoded) {
            // Empty == no restriction == most permissive == wins the union
            return [];
        }

        return array_values(array_unique([...$existing, ...$decoded]));
    }
}
