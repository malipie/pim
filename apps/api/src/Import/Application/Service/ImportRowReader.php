<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Import\Domain\Enum\FileEncoding;
use App\Import\Domain\Exception\InvalidImportFileException;
use Generator;
use League\Csv\Reader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

use const PATHINFO_EXTENSION;

/**
 * Streams an uploaded CSV / xlsx file as `header → value` rows.
 *
 * IMP2-2.1 — reads STREAMING (constant memory regardless of file size):
 * CSV via league/csv `createFromPath` + an iconv read stream-filter (no
 * `file_get_contents` of the whole blob), XLSX via openspout's row iterator
 * (no PhpSpreadsheet full-workbook load). Duplicate headers are deduplicated
 * via {@see HeaderNormalizer} so a repeated column name does not silently
 * overwrite another in the header-keyed row contract.
 *
 * Lives outside {@see ImportValidationService} because the async
 * {@see \App\Import\Application\Handler\ImportRunHandler} needs the same
 * iterator to drive persistence — sharing the reader keeps row numbering +
 * encoding handling in one place.
 */
final readonly class ImportRowReader
{
    private const int DETECT_PREFIX_BYTES = 16384;

    public function __construct(
        private EncodingDetector $encodingDetector,
        private DelimiterDetector $delimiterDetector,
    ) {
    }

    /**
     * Yields each data row keyed by its 1-based data-row index: the header is
     * file row 1, the first data row is yielded as key `1`, the next as `2`, … .
     * Keys are consistent across CSV and XLSX (this is the pre-2.1 contract,
     * preserved verbatim — consumers add the header offset when surfacing a
     * file/Excel row number to the user).
     *
     * @return Generator<int, array<string, string|null>>
     */
    public function read(string $absolutePath, ?FileEncoding $encodingOverride = null, ?string $delimiterOverride = null): Generator
    {
        if (!is_readable($absolutePath)) {
            throw InvalidImportFileException::unreadable($absolutePath);
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        if ('xlsx' === $extension) {
            yield from $this->iterateXlsx($absolutePath);

            return;
        }
        if ('csv' === $extension) {
            yield from $this->iterateCsv($absolutePath, $encodingOverride, $delimiterOverride);

            return;
        }

        throw InvalidImportFileException::unsupportedExtension($extension);
    }

    /**
     * openspout row-streaming. Cells arrive positionally and gap-padded by
     * column reference, so a positional `headers[$i] => row[$i]` mapping stays
     * aligned even when the exporter omits empty cells (#1130) — the same
     * guarantee the old column-letter mapping gave, without loading the sheet.
     *
     * @return Generator<int, array<string, string|null>>
     */
    private function iterateXlsx(string $absolutePath): Generator
    {
        // SHOULD_FORMAT_DATES=false → raw cell values; we coerce to string
        // ourselves so a numeric EAN never picks up a locale format / `.0`.
        $reader = new XlsxReader(new XlsxOptions(SHOULD_FORMAT_DATES: false));
        $reader->open($absolutePath);
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $headers = null;
                $rowNumber = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    ++$rowNumber;
                    $values = $row->toArray();
                    if (null === $headers) {
                        $headers = HeaderNormalizer::deduplicate(array_values(array_map(
                            static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '',
                            $values,
                        )));

                        continue;
                    }

                    $assoc = [];
                    foreach ($headers as $i => $header) {
                        if ('' === $header) {
                            continue;
                        }
                        $assoc[$header] = $this->coerceCell($values[$i] ?? null);
                    }
                    yield $rowNumber - 1 => $assoc;
                }

                break; // first sheet only (sheet-picker is IMP2-3.1)
            }
        } finally {
            $reader->close();
        }
    }

    /**
     * @return Generator<int, array<string, string|null>>
     */
    private function iterateCsv(string $absolutePath, ?FileEncoding $encodingOverride, ?string $delimiterOverride): Generator
    {
        // Detect encoding + delimiter from a bounded prefix (both only need a
        // sample) — never read the whole file into memory.
        $prefix = $this->readPrefix($absolutePath);
        // Surface an empty / unreadable file as a clear error rather than
        // silently yielding zero rows (mirrors FileParserService::parseCsv;
        // an empty prefix would otherwise fall back to a default delimiter).
        if ('' === trim($prefix)) {
            throw InvalidImportFileException::empty();
        }
        $encoding = $encodingOverride ?? $this->encodingDetector->detect($prefix);
        $delimiter = $delimiterOverride ?? $this->delimiterDetector->detect($this->encodingDetector->stripBom($prefix));

        $reader = Reader::from($absolutePath, 'r');
        // Transcode non-UTF-8 input chunk-by-chunk via a read stream filter
        // (delimiters are ASCII, so detection on the raw prefix is valid).
        if (FileEncoding::Utf8 !== $encoding && FileEncoding::Utf8Bom !== $encoding) {
            $reader->appendStreamFilterOnRead('convert.iconv.'.$encoding->iconvName().'/UTF-8//TRANSLIT');
        }
        $reader->setDelimiter($delimiter);

        // Read records POSITIONALLY (integer-keyed) and map against the
        // de-duplicated header ourselves. Passing a header to getRecords() — even
        // a de-duplicated one — still trips league/csv's duplicate-column guard
        // on repeated BLANK columns ('' === ''), which real exports carry
        // (bosch-09-01-2026.csv has several empty header cells). Positional
        // mapping sidesteps the guard and keeps every column addressable
        // (including the suffixed `#2` keys).
        $headers = null;
        $rowNumber = 0;
        foreach ($reader->getRecords() as $record) {
            ++$rowNumber;
            $values = array_values($record);
            if (null === $headers) {
                $headers = HeaderNormalizer::deduplicate(array_map(
                    static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '',
                    $values,
                ));

                continue;
            }

            $assoc = [];
            foreach ($headers as $i => $header) {
                if ('' === $header) {
                    continue;
                }
                $assoc[$header] = $this->coerceCell($values[$i] ?? null);
            }
            yield $rowNumber - 1 => $assoc;
        }
    }

    /**
     * Read up to the first {@see self::DETECT_PREFIX_BYTES} bytes for encoding
     * + delimiter detection without slurping the file.
     */
    private function readPrefix(string $absolutePath): string
    {
        $handle = fopen($absolutePath, 'r');
        if (false === $handle) {
            throw InvalidImportFileException::unreadable($absolutePath);
        }
        try {
            $prefix = fread($handle, self::DETECT_PREFIX_BYTES);
        } finally {
            fclose($handle);
        }

        return false === $prefix ? '' : $prefix;
    }

    private function coerceCell(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }
        $string = \is_scalar($value) ? (string) $value : '';

        return '' === $string ? null : $string;
    }
}
