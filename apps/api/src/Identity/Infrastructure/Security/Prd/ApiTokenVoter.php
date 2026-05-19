<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security\Prd;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\ApiToken;
use App\Identity\Domain\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * RBAC-P3-006 (#669) — per-ApiToken authorization aligned with the
 * PRD §3.2 macierz row.
 *
 * The macierz splits API-token operations onto two codes:
 *
 *   - `api_tokens.own.crud`         — caller manages tokens **they own**,
 *   - `api_tokens.all.view_revoke`  — caller manages tokens across the
 *                                     tenant (Owner / Admin / Integration
 *                                     Manager).
 *
 * Action → permission resolution:
 *   - `create`                     → needs `api_tokens.own.crud`. Class-
 *                                    level subject (`ApiToken` FQCN), no
 *                                    ownership compare.
 *   - `view`, `revoke` (instance)  → granted when EITHER the caller owns
 *                                    the token AND has `own.crud`, OR
 *                                    the caller has `all.view_revoke`.
 *   - `view_all`, `revoke_all`     → needs `api_tokens.all.view_revoke`.
 *                                    Used for the cross-tenant management
 *                                    list endpoint.
 *
 * Subject: {@see ApiToken} carries `tenantId` / `userId` as Uuids (not a
 * Tenant entity), so AbstractPrdVoter's default tenant compare doesn't
 * fit — this voter implements its own check directly. Cross-tenant
 * tokens never authorise even with `all.view_revoke`, because that code
 * is tenant-scoped.
 *
 * @extends Voter<string, ApiToken|class-string<ApiToken>>
 */
final class ApiTokenVoter extends Voter
{
    private const array INSTANCE_ATTRIBUTES = ['view', 'revoke'];
    private const array CLASS_ATTRIBUTES = ['create', 'view_all', 'revoke_all'];

    public function __construct(private readonly PermissionResolverInterface $resolver)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (!\in_array($attribute, [...self::INSTANCE_ATTRIBUTES, ...self::CLASS_ATTRIBUTES], true)) {
            return false;
        }

        if (\is_string($subject)) {
            return ApiToken::class === $subject;
        }

        return $subject instanceof ApiToken;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $permissions = $this->resolver->resolve($user);
        $hasOwnCrud = $permissions->has('api_tokens.own.crud');
        $hasAllViewRevoke = $permissions->has('api_tokens.all.view_revoke');

        return match ($attribute) {
            'create' => $hasOwnCrud,
            'view_all', 'revoke_all' => $hasAllViewRevoke,
            'view', 'revoke' => $this->canActOnToken($subject, $user, $hasOwnCrud, $hasAllViewRevoke),
            default => false,
        };
    }

    private function canActOnToken(
        mixed $subject,
        User $user,
        bool $hasOwnCrud,
        bool $hasAllViewRevoke,
    ): bool {
        if (!$subject instanceof ApiToken) {
            return false;
        }

        if ($subject->getTenantId()->toRfc4122() !== $user->getTenant()->getId()->toRfc4122()) {
            return false;
        }

        if ($hasAllViewRevoke) {
            return true;
        }

        return $hasOwnCrud
            && $subject->getUserId()->toRfc4122() === $user->getId()->toRfc4122();
    }
}
