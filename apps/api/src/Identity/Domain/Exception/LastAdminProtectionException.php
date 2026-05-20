<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use App\Identity\Domain\Entity\User;
use DomainException;

/**
 * RBAC-P3-005 (#668) — thrown when a write that would leave the tenant
 * without an Administrator / Tenant Owner role-bearer is attempted.
 *
 * Surfaces as 409 Conflict on the API edge so the FE can prompt the
 * operator to transfer the role before retrying (see
 * {@see \App\Components\Identity\LastAdminProtectionModal}).
 */
final class LastAdminProtectionException extends DomainException
{
    public static function deactivatingLastAdmin(User $subject): self
    {
        return new self(\sprintf(
            'Cannot deactivate %s — this is the last Administrator on the tenant. Assign the Administrator role to another user first.',
            $subject->getEmail(),
        ));
    }

    public static function removingLastAdminRole(User $subject): self
    {
        return new self(\sprintf(
            'Cannot remove the Administrator role from %s — this is the last Administrator on the tenant. Assign the Administrator role to another user first.',
            $subject->getEmail(),
        ));
    }
}
