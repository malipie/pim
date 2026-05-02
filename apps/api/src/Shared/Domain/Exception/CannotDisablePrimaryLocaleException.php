<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use DomainException;

/**
 * Operator tried to remove the locale currently set as the workspace
 * primary. The flow is: change primary first, then disable the old one.
 *
 * Mapped to 409 RFC 7807 with type `urn:pim:errors:cannot-disable-primary-locale`.
 */
final class CannotDisablePrimaryLocaleException extends DomainException
{
    public function __construct(public readonly string $locale)
    {
        parent::__construct(\sprintf('Cannot disable primary locale "%s". Change primary first.', $locale));
    }
}
