<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\Entity\ObjectType;
use RuntimeException;
use Throwable;

/**
 * VIEW-01 (#372) — raised when DELETE on a custom ObjectType is attempted
 * while it still has live `objects` rows. The Danger zone in the modeling
 * UI guards against this on the FE, but the API enforces the same
 * invariant authoritatively (FE could be bypassed by a direct call).
 *
 * AUD-072 (#1614) — also raised as the safety-net translation of a Postgres
 * `ForeignKeyConstraintViolationException` when a concurrent insert wins the
 * race against the pre-delete count; the original DBAL exception is chained
 * via `$previous` for diagnostics.
 *
 * Mapped to 409 RFC 7807 with type
 * `urn:pim:errors:object-type-has-instances`.
 */
final class ObjectTypeHasInstancesException extends RuntimeException
{
    public function __construct(
        public readonly ObjectType $objectType,
        public readonly int $instanceCount,
        ?Throwable $previous = null,
    ) {
        parent::__construct(\sprintf(
            'ObjectType "%s" has %d instance(s); migrate or delete them before removing the type.',
            $objectType->getCode(),
            $instanceCount,
        ), 0, $previous);
    }
}
