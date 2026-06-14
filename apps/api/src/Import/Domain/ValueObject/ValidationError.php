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
     * IMP2-1.9 — blocking is driven by SEVERITY, not by error type (ADR
     * IMP2-1.1): only an {@see ImportLogLevel::Error} skips the row. Warnings
     * and infos are surfaced in the report but never block — so a re-import of
     * one's own export into a non-empty catalog (DuplicateSkuInFile /
     * DuplicateSkuInDb in CREATE mode, CategoryNotFound, …) degrades to
     * skip-with-warning instead of rejecting every row.
     *
     * Each emitter is responsible for choosing the right level:
     * MissingRequired / InvalidType / InvalidValue → Error; the duplicate and
     * lookup-miss findings → Warning.
     */
    public function isRowBlocking(): bool
    {
        return ImportLogLevel::Error === $this->level;
    }
}
