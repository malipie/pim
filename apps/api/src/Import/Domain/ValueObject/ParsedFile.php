<?php

declare(strict_types=1);

namespace App\Import\Domain\ValueObject;

use App\Import\Domain\Enum\FileEncoding;

/**
 * Result of parsing the uploaded file's first sheet (xlsx) or the
 * whole document (csv).
 *
 * `sampleRows` carries up to 5 rows — enough for the wizard to render
 * sample values per column on Step 2 (mapping). The full body is
 * processed lazily during the validation / async runs in IMP-03/04.
 */
final readonly class ParsedFile
{
    /**
     * @param list<string>            $headers
     * @param list<list<string|null>> $sampleRows
     */
    public function __construct(
        public array $headers,
        public array $sampleRows,
        public int $totalRows,
        public FileEncoding $encoding,
        public ?string $delimiter = null,
        public ?string $sheetName = null,
        public bool $hadMultipleSheets = false,
    ) {
    }
}
