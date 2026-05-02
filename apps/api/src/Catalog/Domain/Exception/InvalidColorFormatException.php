<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Raised when an AttributeOption color is set to a value that is not a
 * valid `#RRGGBB` hex string. Mirrors the DB-level CHECK constraint so
 * invariant violations surface as RFC 7807 422 in the API rather than a
 * raw SQL constraint error.
 */
final class InvalidColorFormatException extends UnprocessableEntityHttpException
{
    public function __construct(string $color)
    {
        parent::__construct(\sprintf('Invalid color "%s" — expected #RRGGBB hex format.', $color));
    }
}
