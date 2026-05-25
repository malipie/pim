<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * ULV-04a (#985) — generic per-ObjectType voter for the universal
 * ObjectListView pipeline.
 *
 * The pre-ULV layout shipped sibling voters per built-in kind
 * ({@see ProductVoter}, {@see CategoryVoter}, {@see AssetVoter}) that
 * each hardcoded their own `{kind}.{action}` PRD code. Custom kinds
 * (per ADR-009 — `kind=custom`) had no voter and therefore no way to
 * gate the universal list endpoint per ObjectType.
 *
 * This voter accepts a `[ObjectType, action]` tuple as its subject and
 * resolves authorization against the new generic `object.{action}` PRD
 * permission codes seeded by {@see \App\DataFixtures\Identity\PrdPermissionFixtures}.
 * Built-in kinds keep working through their existing per-kind voters in
 * parallel — this voter is additive and only fires when the caller
 * explicitly votes against an ObjectType tuple.
 *
 * Tuple subject shape: `[ObjectType $objectType, string $action]` where
 * `$action` is one of {view, add, edit, delete, export} matching the
 * 5 generic PRD codes.
 *
 * Per-ObjectType **grant** scoping (e.g. "user X can view Cars but not
 * Bikes") is deferred to a follow-up RBAC ticket that adds an
 * `object_type_scope` JSON column on `user_role_assignments` alongside
 * the existing `locale_scope` / `channel_scope` / `attribute_group_scope`
 * payloads. The voter shape stays stable across that change.
 *
 * Tenant scope is enforced by the upstream `TenantFilter` Doctrine
 * extension on every `findById(...)` call that hydrates the
 * `ObjectType` — cross-tenant references never reach this voter.
 *
 * @extends Voter<string, array{0: object, 1: string}>
 */
final class ObjectScopedVoter extends Voter
{
    /**
     * @var array<string, string> action → PRD permission code
     */
    private const array PERMISSION_MAP = [
        'view' => 'object.view',
        'add' => 'object.add',
        'edit' => 'object.edit',
        'delete' => 'object.delete',
        'export' => 'object.export',
    ];

    public function __construct(
        private readonly PermissionResolverInterface $resolver,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\array_key_exists($attribute, self::PERMISSION_MAP)) {
            return false;
        }

        if (!\is_array($subject) || 2 !== \count($subject)) {
            return false;
        }

        $objectType = $subject[0] ?? null;
        $action = $subject[1] ?? null;
        if (!\is_object($objectType) || !\is_string($action)) {
            return false;
        }

        // Subject[0] must be the Catalog ObjectType domain entity. Compare
        // by FQCN string so this voter does not need a `use` import from
        // Catalog_Internals (Deptrac scope, see CatalogObjectVoter).
        return 'App\\Catalog\\Domain\\Entity\\ObjectType' === $objectType::class;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $code = self::PERMISSION_MAP[$attribute] ?? null;
        if (null === $code) {
            return false;
        }

        $permissions = $this->resolver->resolve($user);

        return $permissions->has($code);
    }
}
