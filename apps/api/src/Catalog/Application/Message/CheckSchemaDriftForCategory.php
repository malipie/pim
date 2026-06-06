<?php

declare(strict_types=1);

namespace App\Catalog\Application\Message;

/**
 * CHC-05 (#1287) — dispatched after a confirmed category move that affects
 * products. The asynchronous CHC-04 handler re-evaluates each affected
 * product's effective schema against its stored snapshot and flags drift.
 *
 * Routed `async`; with `allow_no_handlers` the dispatch is a no-op enqueue
 * until the CHC-04 handler lands. Carries the moved category id as an
 * RFC-4122 string (matching the project's async-message convention — no Uuid
 * objects across the transport boundary).
 */
final readonly class CheckSchemaDriftForCategory
{
    public function __construct(
        public string $categoryId,
    ) {
    }
}
