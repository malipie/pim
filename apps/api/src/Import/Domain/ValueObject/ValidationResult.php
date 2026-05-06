<?php

declare(strict_types=1);

namespace App\Import\Domain\ValueObject;

/**
 * Aggregate of one dry-run pass: how many rows would land vs. skip,
 * plus the per-row findings. The wizard renders the first 10 errors
 * inline and offers a "show all" modal that reads the same list.
 */
final readonly class ValidationResult
{
    /**
     * @param list<ValidationError> $errors
     */
    public function __construct(
        public int $totalRows,
        public int $successCount,
        public int $errorCount,
        public array $errors,
    ) {
    }
}
