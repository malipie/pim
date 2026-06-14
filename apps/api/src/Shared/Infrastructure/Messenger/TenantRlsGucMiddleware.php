<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Application\TenantAwareMessage;
use App\Shared\Infrastructure\Messenger\Stamp\TenantStamp;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;
use Symfony\Component\Uid\Uuid;

/**
 * IMP2-2.5 (#1481) — sets the Postgres GUC `app.current_tenant` that RLS
 * policies read, for the duration of an async handler running in a worker.
 *
 * {@see \App\Identity\Infrastructure\Doctrine\RlsContextListener} only fires on
 * `kernel.request`, so worker processes never establish the GUC and would
 * return zero rows from (or fail writes against) RLS-protected tables once
 * FORCE RLS lands. {@see TenantContextRebindingMiddleware} already rebinds the
 * PHP-side {@see \App\Shared\Application\TenantContext} (TenantFilter works in
 * workers) — this middleware closes the remaining database-policy gap and runs
 * directly after it.
 *
 * The GUC is set at **session** level (`is_local = false`), not transaction
 * level: an import handler commits many chunks across several transactions
 * within a single message (IMP2-2.3 checkpointing), and a transaction-local
 * value would vanish on the first commit. A `finally` resets it so a pooled
 * connection never leaks the tenant into the next message — and the next
 * message re-sets it on entry regardless.
 *
 * Synchronous dispatches (no `ConsumedByWorkerStamp`) skip the middleware: the
 * request listener is the source of truth there.
 */
final readonly class TenantRlsGucMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $envelope->last(ConsumedByWorkerStamp::class)) {
            // Sync dispatch — RlsContextListener already set the GUC from the
            // HTTP request principal.
            return $stack->next()->handle($envelope, $stack);
        }

        $tenantId = $this->resolveTenantId($envelope);
        if (null === $tenantId) {
            // A worker message with no tenant source already tripped
            // TenantContextRebindingMiddleware (which throws); nothing to do.
            return $stack->next()->handle($envelope, $stack);
        }

        // tenant-safe: infrastructure (establishes the tenant_id RLS policies read in workers; this IS the tenant boundary, not a bypass)
        $this->connection->executeStatement(
            "SELECT set_config('app.current_tenant', :tenant_id, false)",
            ['tenant_id' => $tenantId->toRfc4122()],
        );
        // tenant-safe: infrastructure (workers never run as super-admin; pin the bypass flag off in case the pooled connection carried it)
        $this->connection->executeStatement("SELECT set_config('app.is_super_admin', 'false', false)");

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            // Defence in depth: a long-lived worker reuses the DBAL connection,
            // so clear the session GUC before the next message is handled.
            // tenant-safe: infrastructure (resets the RLS tenant marker so a pooled connection cannot leak the previous tenant)
            $this->connection->executeStatement("SELECT set_config('app.current_tenant', '', false)");
        }
    }

    private function resolveTenantId(Envelope $envelope): ?Uuid
    {
        $stamp = $envelope->last(TenantStamp::class);
        if ($stamp instanceof TenantStamp) {
            return $stamp->tenantId;
        }

        $message = $envelope->getMessage();
        if ($message instanceof TenantAwareMessage) {
            return $message->tenantId();
        }

        return null;
    }
}
