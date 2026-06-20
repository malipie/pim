<?php

declare(strict_types=1);

namespace App\Identity\Contracts\Auth;

use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

/**
 * AUD-053 (W3-1) — cross-context seam for "who is the current user".
 *
 * Controllers and services outside the Identity bundle repeatedly reach for
 * {@see \App\Identity\Domain\Entity\User} just to pull the authenticated
 * principal's id (and, less often, its tenant) off the Symfony security
 * token — an `Identity_Internals` leak that Deptrac baselines today (see the
 * `App\Identity\Domain\Entity\User` cluster in `deptrac.yaml`).
 *
 * This contract exposes the minimum surface those callers need so they can
 * stop importing the concrete entity. The current principal comes from the
 * security token via the adapter; callers do not pass the user explicitly —
 * same shape as {@see \App\Identity\Contracts\Policy\AttributePermissionReader}.
 *
 * Both accessors return `null` for anonymous principals (no authenticated
 * domain user). Callers that require authentication assert on the `null` and
 * raise their own 401, exactly as the pre-seam `instanceof User` guard did.
 *
 * {@see Tenant} lives in `Shared` so returning it keeps the contract within
 * the `Identity_Contracts → Shared` allowance.
 */
interface CurrentUserProvider
{
    /**
     * The authenticated domain user's id, or `null` when the principal is
     * anonymous / not a domain {@see \App\Identity\Domain\Entity\User}
     * (e.g. an API-key principal or an unauthenticated request).
     */
    public function userId(): ?Uuid;

    /**
     * The authenticated domain user's tenant, or `null` when there is no
     * domain user on the token.
     */
    public function tenant(): ?Tenant;
}
