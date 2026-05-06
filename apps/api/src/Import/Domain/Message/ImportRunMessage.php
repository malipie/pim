<?php

declare(strict_types=1);

namespace App\Import\Domain\Message;

use Symfony\Component\Uid\Uuid;

/**
 * Async marker — the Symfony Messenger transport routes this to the
 * `imports` queue (see config/packages/messenger.yaml). The handler
 * loads the {@see \App\Import\Domain\Entity\ImportSession} by id and
 * runs the chunked persistence loop.
 */
final readonly class ImportRunMessage
{
    public function __construct(
        public Uuid $importSessionId,
        public Uuid $tenantId,
    ) {
    }
}
