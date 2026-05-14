<?php

declare(strict_types=1);

namespace App\Export\Domain\Enum;

/**
 * Output formats supported in MVP (PRD §13.1). XML/JSON deferred to
 * Faza 1 (§13.2). XLSX is the round-trip target with Excel; CSV is
 * the lighter alternative for Marcin's Python pipeline.
 */
enum ExportFormat: string
{
    case Xlsx = 'xlsx';
    case Csv = 'csv';

    public function mimeType(): string
    {
        return match ($this) {
            self::Xlsx => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Csv => 'text/csv',
        };
    }

    public function extension(): string
    {
        return $this->value;
    }
}
