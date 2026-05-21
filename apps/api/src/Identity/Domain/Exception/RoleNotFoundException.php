<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use DomainException;

/**
 * Manual user creation (#867) — thrown when `POST /api/users` references a
 * role code that doesn't exist in the calling tenant. Surfaces as 400 Bad
 * Request (caller supplied invalid input, not a conflict).
 */
final class RoleNotFoundException extends DomainException
{
    public static function forCode(string $code): self
    {
        return new self(\sprintf('Role with code "%s" not found in tenant.', $code));
    }
}
