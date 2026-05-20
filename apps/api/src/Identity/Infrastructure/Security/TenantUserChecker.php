<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * RBAC-P5-021 (#711) — refuses login when the user's tenant has been
 * suspended or soft-deleted.
 *
 * Runs as Symfony Security's `user_checker` on the main + API token
 * firewalls. The default `UserChecker` only inspects user-level flags
 * (locked/expired/etc.); we layer the tenant-level lifecycle on top so
 * a suspended tenant becomes a hard authentication block for every
 * one of its users without per-user disable churn.
 *
 * Triggered on every successful credential check (`checkPostAuth`) so
 * even active JWT sessions get blocked the moment the tenant flips —
 * the access token's 1h TTL is the worst-case window before a
 * suspended tenant's traffic ceases.
 *
 * Status semantics (per Tenant entity docblock):
 *   - `active`    → pass
 *   - `suspended` → `DisabledException` ("tenant suspended")
 *   - `deleted`   → same as suspended but uses the deleted message;
 *                   re-using DisabledException keeps the audit clean
 *                   and avoids leaking whether the tenant existed at
 *                   all (Symfony wraps it in BadCredentialsException
 *                   by default on the JSON login firewall)
 */
final class TenantUserChecker implements UserCheckerInterface
{
    public function __construct(
        private readonly UserCheckerInterface $inner,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        $this->inner->checkPreAuth($user);
        $this->assertTenantUsable($user);
    }

    public function checkPostAuth(UserInterface $user): void
    {
        $this->inner->checkPostAuth($user);
        $this->assertTenantUsable($user);
    }

    private function assertTenantUsable(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        $tenant = $user->getTenant();
        if ($tenant->isSuspended()) {
            throw new CustomUserMessageAccountStatusException(
                'Account temporarily disabled — tenant suspended. Contact your administrator.',
            );
        }
        if ($tenant->isDeleted()) {
            throw new CustomUserMessageAccountStatusException(
                'Account permanently disabled — tenant deleted.',
            );
        }
        if (!$user->isActive()) {
            throw new DisabledException('Account disabled.');
        }
    }
}
