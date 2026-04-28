<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\Entity\ObjectType;
use RuntimeException;

/**
 * Raised when a destructive operation targets a built-in ObjectType row
 * (`is_built_in=true`). Predefined Product / Category / Asset fixtures are
 * platform-owned and must not be removed by a tenant — RLS + service guard
 * both reject the attempt.
 */
final class BuiltInObjectTypeException extends RuntimeException
{
    public static function cannotDelete(ObjectType $objectType): self
    {
        return new self(\sprintf(
            'ObjectType "%s" (kind=%s) is built-in and cannot be deleted.',
            $objectType->getCode(),
            $objectType->getKind()->value,
        ));
    }
}
