<?php

declare(strict_types=1);

namespace App\Identity\Domain\Exception;

use DomainException;

/**
 * Manual user creation (#867) — thrown when `POST /api/users` attempts to
 * create a user with an email that already exists in the tenant. Surfaces
 * as 409 Conflict on the API edge so the FE can show a contextual toast.
 */
final class DuplicateUserEmailException extends DomainException
{
    public static function forEmail(string $email): self
    {
        return new self(\sprintf('User with email "%s" already exists.', $email));
    }
}
