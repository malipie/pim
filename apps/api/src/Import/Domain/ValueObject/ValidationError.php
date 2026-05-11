<?php

declare(strict_types=1);

namespace App\Import\Domain\ValueObject;

use App\Import\Domain\Enum\ImportErrorType;
use App\Import\Domain\Enum\ImportLogLevel;

/**
 * Single per-row validation finding. Mirrors the {@see \App\Import\Domain\Entity\ImportLog}
 * shape so the dry-run preview and the persisted log share columns 1:1.
 */
final readonly class ValidationError
{
    public function __construct(
        public int $rowNumber,
        public ?string $sku,
        public ImportErrorType $errorType,
        public ImportLogLevel $level,
        public string $message,
        public ?string $columnName = null,
        public ?string $columnValue = null,
    ) {
    }

    /**
     * Returns false for findings that the operator wants surfaced in
     * the report but should NOT skip the row (e.g. a category that the
     * lookup did not resolve — the product still imports, just without
     * the assignment).
     */
    public function isRowBlocking(): bool
    {
        return ImportErrorType::CategoryNotFound !== $this->errorType;
    }
}
