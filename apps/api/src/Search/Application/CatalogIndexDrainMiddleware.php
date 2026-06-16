<?php

declare(strict_types=1);

namespace App\Search\Application;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

/**
 * IMP2-2.6 — drains the {@see CatalogIndexCollector} into Meilisearch after a
 * WORKER message is handled. HTTP/console paths drain on kernel/console.terminate
 * ({@see CatalogIndexFlushSubscriber}), but a Messenger worker fires neither
 * per message, so bulk async ops (import, bulk-edit/-delete) would queue
 * upserts that get reset between messages and never reach search.
 *
 * Implemented as a middleware rather than a WorkerMessageHandledEvent
 * subscriber on purpose: the drain reads each object's `attributes_indexed`
 * through the tenant filter, and the worker event fires AFTER
 * {@see \App\Shared\Infrastructure\Messenger\TenantContextRebindingMiddleware}
 * clears the tenant in its finally. Registered AFTER that rebinding (and the
 * RLS-GUC middleware) and BEFORE doctrine_transaction, so this finally runs
 * while the tenant + RLS GUC are still set and after the handler's writes have
 * committed.
 *
 * Sync dispatches (no {@see ConsumedByWorkerStamp}) pass straight through — the
 * request's kernel.terminate listener owns the drain there, so this never
 * double-flushes.
 */
final readonly class CatalogIndexDrainMiddleware implements MiddlewareInterface
{
    public function __construct(
        private CatalogIndexFlusher $flusher,
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $envelope->last(ConsumedByWorkerStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->flusher->flush();
        }
    }
}
