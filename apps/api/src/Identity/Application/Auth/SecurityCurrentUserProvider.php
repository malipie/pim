<?php

declare(strict_types=1);

namespace App\Identity\Application\Auth;

use App\Identity\Contracts\Auth\CurrentUserProvider;
use App\Identity\Domain\Entity\User;
use App\Shared\Domain\Tenant;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Uid\Uuid;

/**
 * AUD-053 (W3-1) — adapter wiring the {@see CurrentUserProvider} contract to
 * the Symfony security token. Mirrors {@see \App\Identity\Application\Policy\SecurityAttributePermissionReader}.
 *
 * Anonymous principals (no token / non-{@see User} principal such as an
 * API-key) resolve to `null`, so callers keep their explicit auth guard.
 */
final readonly class SecurityCurrentUserProvider implements CurrentUserProvider
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function userId(): ?Uuid
    {
        return $this->currentUser()?->getId();
    }

    public function tenant(): ?Tenant
    {
        return $this->currentUser()?->getTenant();
    }

    private function currentUser(): ?User
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user : null;
    }
}
