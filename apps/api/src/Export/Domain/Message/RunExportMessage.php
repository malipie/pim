<?php

declare(strict_types=1);

namespace App\Export\Domain\Message;

use Symfony\Component\Uid\Uuid;

/**
 * EXP-06 (#585) — Symfony Messenger envelope for async exports.
 *
 * Dispatched by the {@see \App\Export\Presentation\Controller\SyncExportController}
 * when target_count crosses the sync threshold (PRD §11.4). Carries
 * only the session UUID — the handler loads the persisted state to
 * make the message replay-safe (recoverable retries do not re-run on
 * stale config).
 */
final class RunExportMessage
{
    public function __construct(
        public readonly Uuid $exportSessionId,
    ) {
    }
}
