<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

/**
 * Mutable success/skipped/error accumulator threaded through a bulk run.
 *
 * Replaces the three `$success`/`$skipped`/`$errors` locals that every
 * Bulk*Handler previously declared and incremented inline. Passing a
 * shared object into {@see AbstractBulkHandler::runBatch()} lets the
 * base class own the loop + session finalisation while each handler's
 * per-object body keeps full control over how it tallies a row (some
 * bump `skipped` per-edit and `success` once-per-row — see
 * BulkMultiAttributeEditHandler).
 */
final class BulkCounters
{
    public function __construct(
        public int $success = 0,
        public int $skipped = 0,
        public int $error = 0,
    ) {
    }

    /**
     * @return array{success: int, skipped: int, error: int}
     */
    public function toResult(): array
    {
        return ['success' => $this->success, 'skipped' => $this->skipped, 'error' => $this->error];
    }
}
