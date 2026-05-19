<?php

declare(strict_types=1);

namespace App\Identity\Application\Policy;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use InvalidArgumentException;

/**
 * RBAC-P3-010 (#673) — own / all scope resolution for the operational
 * resources that carry an ownership dimension per PRD §3.2:
 *
 *   - `imports`     — `imports.view_own` / `imports.view_all`,
 *   - `exports`     — `exports.view_own` / `exports.view_all`,
 *   - `multimedia`  — `multimedia.add_edit_own` /
 *                     `multimedia.add_edit_any`,
 *   - `api_tokens`  — `api_tokens.own.crud` /
 *                     `api_tokens.all.view_revoke`,
 *   - `audit`       — `audit.view_own` / `audit.view_cross_user`.
 *
 * The policy is intentionally **not** applied to domain resources
 * (products, categories) — those are tenant-scoped only; ownership has
 * no semantics there (per ticket discussion).
 *
 * `?scope=own|all` query-parameter interpretation:
 *
 *   - `?scope=all` + user has `*.view_all` → repository filters by
 *                     tenant only;
 *   - `?scope=all` + user has only `*.view_own` → caller denied
 *                     (`canRequestScope(All) === false`);
 *   - `?scope=own` or absent + user has `*.view_own` → repository
 *                     filters by tenant AND created_by/uploaded_by/
 *                     user_id (column varies per resource);
 *   - both owns + all granted → default `own`; calling
 *                     `effectiveScope(?'all')` returns `All` only when
 *                     the caller explicitly asked.
 *
 * The column-mapping concern (created_by vs uploaded_by vs user_id)
 * lives at the repository layer per resource — this policy decides
 * whether the scope is *allowed*; the query layer applies the right
 * WHERE clause.
 */
final readonly class OwnershipPolicy
{
    /**
     * Mapping (resource → [own_code, all_code]) for every resource type
     * with ownership semantics in MVP. Adding a new resource means one
     * entry here plus a controller-side `?scope=` interpretation.
     */
    private const array RESOURCE_CODES = [
        'imports' => ['imports.view_own', 'imports.view_all'],
        'exports' => ['exports.view_own', 'exports.view_all'],
        'multimedia' => ['multimedia.add_edit_own', 'multimedia.add_edit_any'],
        'api_tokens' => ['api_tokens.own.crud', 'api_tokens.all.view_revoke'],
        'audit' => ['audit.view_own', 'audit.view_cross_user'],
    ];

    public function __construct(private PermissionResolverInterface $resolver)
    {
    }

    public function canViewOwn(User $user, string $resourceType): bool
    {
        [$ownCode] = $this->codesFor($resourceType);

        return $this->resolver->resolve($user)->has($ownCode);
    }

    public function canViewAll(User $user, string $resourceType): bool
    {
        [, $allCode] = $this->codesFor($resourceType);

        return $this->resolver->resolve($user)->has($allCode);
    }

    /**
     * Returns the scope the caller can actually execute. If the caller
     * lacks even `*.view_own`, returns null (controller responds 403).
     * Otherwise:
     *   - requested === Scope::All + caller has all_code → Scope::All,
     *   - requested === Scope::All + caller has only own_code → null
     *     (controller responds 403),
     *   - requested === Scope::Own / null + caller has own_code →
     *     Scope::Own,
     *   - requested === Scope::Own / null + caller has only all_code →
     *     Scope::Own (own ⊆ all, the broader-scope only role still
     *     sees their own data).
     */
    public function effectiveScope(User $user, string $resourceType, ?OwnershipScope $requested): ?OwnershipScope
    {
        [$ownCode, $allCode] = $this->codesFor($resourceType);
        $permissions = $this->resolver->resolve($user);
        $hasOwn = $permissions->has($ownCode);
        $hasAll = $permissions->has($allCode);

        if (!$hasOwn && !$hasAll) {
            return null;
        }

        if (OwnershipScope::All === $requested) {
            return $hasAll ? OwnershipScope::All : null;
        }

        // requested === Own or null → default to own. own ⊆ all so a
        // caller with only all_code still sees their own rows.
        return OwnershipScope::Own;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function codesFor(string $resourceType): array
    {
        if (!\array_key_exists($resourceType, self::RESOURCE_CODES)) {
            throw new InvalidArgumentException(\sprintf(
                'Unknown ownership resource type "%s". Known: %s.',
                $resourceType,
                implode(', ', array_keys(self::RESOURCE_CODES)),
            ));
        }

        return self::RESOURCE_CODES[$resourceType];
    }
}
