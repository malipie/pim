<?php

declare(strict_types=1);

namespace App\Shared\Domain\Exception;

use DomainException;

/**
 * Locale code is not present in `LocaleLibrary::CODES`.
 *
 * Surfaced through `WorkspaceController` as 400 RFC 7807 with type
 * `urn:pim:errors:invalid-locale`.
 */
final class InvalidLocaleException extends DomainException
{
    public function __construct(public readonly string $locale)
    {
        parent::__construct(\sprintf('Locale "%s" is not supported.', $locale));
    }
}
