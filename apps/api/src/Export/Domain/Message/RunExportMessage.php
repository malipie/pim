<?php

declare(strict_types=1);

namespace App\Export\Domain\Message;

use App\Shared\Application\TenantAwareMessage;
use Symfony\Component\Uid\Uuid;

/**
 * EXP-06 (#585) — Symfony Messenger envelope for async exports.
 *
 * Dispatched by the {@see \App\Export\Presentation\Controller\SyncExportController}
 * when target_count crosses the sync threshold (PRD §11.4). Carries
 * the session UUID — the handler loads the persisted state to make the
 * message replay-safe (recoverable retries do not re-run on stale
 * config).
 *
 * Implements {@see TenantAwareMessage} so the
 * {@see \App\Shared\Infrastructure\Messenger\TenantContextRebindingMiddleware}
 * can rebind the tenant on the worker process (HIGH-002): the request
 * listener that binds TenantContext from the JWT principal never fires
 * on an async worker boot, so the payload must declare its tenant or
 * the middleware aborts the run before the handler loads the session.
 */
final class RunExportMessage implements TenantAwareMessage
{
    public function __construct(
        public readonly Uuid $exportSessionId,
        public readonly Uuid $tenantId,
    ) {
    }

    public function tenantId(): Uuid
    {
        return $this->tenantId;
    }
}
