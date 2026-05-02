<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use RuntimeException;

/**
 * VIEW-01 (#372) — raised when the wizard / duplicate flow tries to
 * create an ObjectType with a code already used in the same tenant.
 *
 * Mapped to 409 RFC 7807 with type
 * `urn:pim:errors:object-type-code-conflict`. The FE highlights the code
 * field and renders a translated message via the `conflict_code_taken`
 * key.
 */
final class ObjectTypeCodeConflictException extends RuntimeException
{
    public function __construct(public readonly string $conflictingCode)
    {
        parent::__construct(\sprintf('ObjectType code "%s" is already used in this tenant.', $conflictingCode));
    }
}
