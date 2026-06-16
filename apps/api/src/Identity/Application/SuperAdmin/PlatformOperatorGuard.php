<?php

declare(strict_types=1);

namespace App\Identity\Application\SuperAdmin;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Exception\PermissionDeniedException;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * AUD-003 (#1575) — single gate for the platform operator panel.
 *
 * Before this guard existed, the `/api/admin/tenants/*` and
 * `/api/admin/break-glass/*` controllers gated on the `super_admin`
 * ROLE CODE. Fixtures grant that global role to every tenant Owner, so an
 * Owner could list / read / suspend / delete competitor tenants and invoke
 * break-glass. The role code is the wrong signal — authority must be
 * carried by a platform-scope PERMISSION (`platform.*`) that only the
 * dedicated `platform_operator` role holds (PRD-PIM-rbac §3.2 cross-tenant
 * block).
 *
 * The guard resolves the authenticated user's permission set through the
 * shared {@see PermissionResolverInterface} (same path the
 * EndpointGuardListener uses) and throws {@see PermissionDeniedException}
 * — rendered as RFC 7807 problem+json by PermissionDeniedProblemListener —
 * when the platform permission is absent. On success it returns the
 * {@see User} so callers keep the principal for the cross-tenant audit
 * stamp.
 */
final readonly class PlatformOperatorGuard
{
    public function __construct(
        private Security $security,
        private PermissionResolverInterface $resolver,
    ) {
    }

    /**
     * @throws PermissionDeniedException when no domain user is authenticated
     *                                   or the user lacks $permissionCode
     */
    public function require(string $permissionCode): User
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            // Anonymous / non-domain principal. Mirror the EndpointGuard
            // contract: surface 403 with the missing code rather than a
            // bare 401 so SPA logic stays uniform.
            throw new PermissionDeniedException($permissionCode);
        }

        if (!$this->resolver->resolve($user)->has($permissionCode)) {
            throw new PermissionDeniedException($permissionCode);
        }

        return $user;
    }
}
