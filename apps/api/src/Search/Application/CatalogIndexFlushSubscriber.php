<?php

declare(strict_types=1);

namespace App\Search\Application;

use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * PROD-03 — drains the {@see CatalogIndexCollector} once the response is
 * on the wire so a single request always issues one batched
 * `addDocuments` per kind to Meilisearch instead of N round-trips.
 *
 * Listens to two events:
 *   - `kernel.terminate` for HTTP requests (response sent, fpm/franken
 *     keeps the worker alive while we drain).
 *   - `console.terminate` for CLI commands (e.g. fixtures, ad-hoc
 *     seeders). Bulk flows already opt out via {@see BulkContext} so
 *     this only affects small-scale CLI ops.
 *
 * Failures are absorbed inside {@see CatalogObjectIndexer::indexBatch()}
 * — a Meili outage must not bubble out of `kernel.terminate` (the
 * response is already gone; throwing here just leaks the exception
 * into the worker log without recourse).
 */
final readonly class CatalogIndexFlushSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CatalogIndexFlusher $flusher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Run after RequestTenantSubscriber clears tenant (-255). We
            // need to drain BEFORE tenant clear because the indexer reads
            // through the tenant filter when fetching CatalogObject rows.
            // -200 is between the default (0) and the tenant clear (-255).
            KernelEvents::TERMINATE => ['onKernelTerminate', -200],
            ConsoleEvents::TERMINATE => 'onConsoleTerminate',
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $this->flusher->flush();
    }

    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $this->flusher->flush();
    }
}
