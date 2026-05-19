<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security\Prd;

use App\Identity\Domain\Entity\User;
use App\Identity\Infrastructure\Security\AbstractPrdVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * RBAC-P3-005 (#668) — per-User authorization aligned with the
 * PRD §3.2 macierz Settings → Users single-permission row
 * (`settings.users.manage`).
 *
 * Macierz collapses every CRUD-style user operation onto one Tenant
 * Owner / Administrator permission. The voter maps each operational
 * action (view / invite / edit / deactivate / reactivate / change_roles)
 * to the same code and adds one runtime invariant:
 *
 *   - **self-modification protection** — a user is allowed to update
 *     their own profile but never their own role set, because that
 *     would be a privilege-escalation path through a single API call.
 *     The voter denies `change_roles` whenever the subject matches the
 *     authenticated user, regardless of the `settings.users.manage`
 *     permission.
 *
 * Deferred to a follow-up (documented in PR description):
 *   - Last-admin protection (`deactivate` / `change_roles` on the only
 *     remaining user holding `settings.users.manage`) — requires a query
 *     against the role membership graph and is more naturally enforced
 *     at the service layer, where a 409 with the explanation message
 *     belongs.
 *   - Owner uniqueness (`change_roles` assigning `tenant_owner` while
 *     it is already held) — same reasoning.
 *
 * Both runtime invariants stack on top of this voter once their support
 * services land; until then the broad gate + self-modification carry
 * the authorization layer.
 */
final class UserVoter extends AbstractPrdVoter
{
    /**
     * @return array<string, string>
     */
    protected function permissionMap(): array
    {
        return [
            'view' => 'settings.users.manage',
            'invite' => 'settings.users.manage',
            'edit' => 'settings.users.manage',
            'deactivate' => 'settings.users.manage',
            'reactivate' => 'settings.users.manage',
            'change_roles' => 'settings.users.manage',
        ];
    }

    protected function subjectClass(): string
    {
        return User::class;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!parent::voteOnAttribute($attribute, $subject, $token)) {
            return false;
        }

        if ('change_roles' !== $attribute) {
            return true;
        }

        $current = $token->getUser();
        if (!$current instanceof User || !$subject instanceof User) {
            return true;
        }

        // Self-modification block — granted upstream by the macierz, but
        // we deny here so neither REST nor GraphQL nor a future agent
        // can be coerced into escalating a session through "edit my own
        // role".
        return $current->getId()->toRfc4122() !== $subject->getId()->toRfc4122();
    }
}
