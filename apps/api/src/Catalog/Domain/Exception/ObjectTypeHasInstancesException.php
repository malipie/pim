<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\Entity\ObjectType;
use RuntimeException;

/**
 * VIEW-01 (#372) — raised when DELETE on a custom ObjectType is attempted
 * while it still has live `objects` rows. The Danger zone in the modeling
 * UI guards against this on the FE, but the API enforces the same
 * invariant authoritatively (FE could be bypassed by a direct call).
 *
 * Mapped to 409 RFC 7807 with type
 * `urn:pim:errors:object-type-has-instances`.
 */
final class ObjectTypeHasInstancesException extends RuntimeException
{
    public function __construct(
        public readonly ObjectType $objectType,
        public readonly int $instanceCount,
    ) {
        parent::__construct(\sprintf(
            'ObjectType "%s" has %d instance(s); migrate or delete them before removing the type.',
            $objectType->getCode(),
            $instanceCount,
        ));
    }
}
