<?php

declare(strict_types=1);

namespace App\Shared\Application;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Base class for Symfony Messenger handlers that process batches of entities.
 *
 * Why this exists: in FrankenPHP worker mode the PHP process lives between
 * requests, so Doctrine's identity map keeps every persisted object across
 * `flush()` calls. Without an explicit `clear()` after each batch a 50k-SKU
 * import drives the worker into OOM (R-25 — Krytyczny, sekcja 3.10
 * architektury). Subclasses MUST funnel batch flushes through
 * {@see flushAndClear()} instead of calling `EntityManagerInterface::flush()`
 * directly inside a loop.
 *
 * The pattern is validated end-to-end by `pim:benchmark:bulk-import`
 * (ticket 0.0.13): 5 000 inserts at batch size 200 stay below 256 MiB peak.
 */
abstract class AbstractBatchHandler
{
    public function __construct(
        protected readonly EntityManagerInterface $entityManager,
        protected readonly int $batchSize = 200,
    ) {
    }

    /**
     * Flush pending changes and detach every managed entity from the unit of
     * work so the identity map cannot accumulate across batches.
     */
    final protected function flushAndClear(): void
    {
        $this->entityManager->flush();
        $this->entityManager->clear();
    }

    /**
     * True when the running counter has reached a batch boundary and the
     * caller should invoke {@see flushAndClear()}.
     */
    final protected function shouldFlush(int $processed): bool
    {
        return $processed > 0 && 0 === $processed % $this->batchSize;
    }
}
