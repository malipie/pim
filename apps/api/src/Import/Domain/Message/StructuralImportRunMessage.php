<?php

declare(strict_types=1);

namespace App\Import\Domain\Message;

use Symfony\Component\Uid\Uuid;

/**
 * Async marker for a structural import (attribute / attribute-group
 * definitions). Routed to the `import` transport (see
 * config/packages/messenger.yaml); the handler loads the
 * {@see \App\Import\Domain\Entity\ImportSession} by id and creates/updates
 * the configuration entities. Mirrors {@see ImportRunMessage} but is handled
 * by {@see \App\Import\Application\Handler\StructuralImportRunHandler}.
 */
final readonly class StructuralImportRunMessage
{
    public function __construct(
        public Uuid $importSessionId,
        public Uuid $tenantId,
    ) {
    }
}
