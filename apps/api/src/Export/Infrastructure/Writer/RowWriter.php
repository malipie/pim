<?php

declare(strict_types=1);

namespace App\Export\Infrastructure\Writer;

/**
 * Minimal writer contract shared by XLSX + CSV implementations.
 *
 * Keeps the sync runner / async handler decoupled from format-specific
 * libraries. Both formats agree on a flat row-of-strings shape; format
 * particulars (BOM, styling, ZIP packing) stay inside the implementation.
 */
interface RowWriter
{
    /**
     * @param list<string> $headers
     */
    public function writeHeaders(array $headers): void;

    /**
     * @param list<string> $values
     */
    public function writeRow(array $values): void;

    public function close(): void;
}
