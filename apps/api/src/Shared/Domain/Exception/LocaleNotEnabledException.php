<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use DomainException;

/**
 * Operator tried to set a primary locale that is not in the enabled list.
 * Use `enableLocale()` first or pick from the enabled set.
 *
 * Mapped to 400 RFC 7807 with type `urn:pim:errors:locale-not-enabled`.
 */
final class LocaleNotEnabledException extends DomainException
{
    public function __construct(public readonly string $locale)
    {
        parent::__construct(\sprintf('Locale "%s" is not enabled in this workspace.', $locale));
    }
}
