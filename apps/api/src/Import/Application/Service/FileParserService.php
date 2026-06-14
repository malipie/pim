<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Import\Domain\Enum\FileEncoding;
use App\Import\Domain\Exception\InvalidImportFileException;
use App\Import\Domain\ValueObject\ParsedFile;
use League\Csv\Reader;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use Throwable;

use const PATHINFO_EXTENSION;

/**
 * Parses an uploaded CSV / xlsx file and returns the headers, the
 * first 5 rows, and the total row count. Single-purpose by design:
 * the validation + async run handlers (IMP-03 / IMP-04) re-open the
 * file streamingly, so this service stays focused on the wizard's
 * Step 1 + Step 2 needs.
 *
 * xlsx behaviour (spec §7.1): only the first sheet is read; if the
 * workbook ships multiple sheets, the {@see ParsedFile::$hadMultipleSheets}
 * flag tells the wizard to surface the warning copy.
 */
final class FileParserService
{
    private const int SAMPLE_ROWS = 5;
    private const int DETECT_PREFIX_BYTES = 16384;
    private const array XLSX_EXTENSIONS = ['xlsx'];
    private const array CSV_EXTENSIONS = ['csv'];

    public function __construct(
        private readonly EncodingDetector $encodingDetector,
        private readonly DelimiterDetector $delimiterDetector,
    ) {
    }

    public function parse(string $absolutePath, ?FileEncoding $encodingOverride = null, ?string $delimiterOverride = null): ParsedFile
    {
        if (!is_readable($absolutePath)) {
            throw InvalidImportFileException::unreadable($absolutePath);
        }

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        if (\in_array($extension, self::XLSX_EXTENSIONS, true)) {
            return $this->parseXlsx($absolutePath);
        }
        if (\in_array($extension, self::CSV_EXTENSIONS, true)) {
            return $this->parseCsv($absolutePath, $encodingOverride, $delimiterOverride);
        }

        throw InvalidImportFileException::unsupportedExtension($extension);
    }

    private function parseXlsx(string $absolutePath): ParsedFile
    {
        // IMP2-2.1 — stream the first sheet with openspout (no full-workbook
        // load). Header dedup mirrors ImportRowReader so the wizard maps against
        // the exact labels the run keys cells by.
        try {
            $reader = new XlsxReader(new XlsxOptions(SHOULD_FORMAT_DATES: false));
            $reader->open($absolutePath);
        } catch (Throwable $exception) {
            throw InvalidImportFileException::corrupted($absolutePath, $exception);
        }

        /** @var list<string> $headers */
        $headers = [];
        /** @var list<list<string|null>> $sampleRows */
        $sampleRows = [];
        $totalRows = 0;
        $sheetName = null;
        $sheetCount = 0;

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                ++$sheetCount;
                if ($sheetCount > 1) {
                    continue; // only the first sheet is read; count the rest for the flag
                }
                $sheetName = $sheet->getName();
                $rowNumber = 0;
                foreach ($sheet->getRowIterator() as $row) {
                    ++$rowNumber;
                    $values = $row->toArray();
                    if (1 === $rowNumber) {
                        $headers = HeaderNormalizer::deduplicate(array_values(array_map(
                            static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '',
                            $values,
                        )));

                        continue;
                    }
                    ++$totalRows;
                    if (\count($sampleRows) < self::SAMPLE_ROWS) {
                        $sampleRows[] = $this->positionalSample($headers, $values);
                    }
                }
            }
        } finally {
            $reader->close();
        }

        if ([] === $headers || [] === array_filter($headers, static fn (string $h): bool => '' !== $h)) {
            throw InvalidImportFileException::noHeaderRow();
        }

        return new ParsedFile(
            headers: $headers,
            sampleRows: $sampleRows,
            totalRows: $totalRows,
            encoding: FileEncoding::Utf8,
            delimiter: null,
            sheetName: $sheetName,
            hadMultipleSheets: $sheetCount > 1,
        );
    }

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

    /**
     * Build a positional preview row aligned to the (deduplicated) header list.
     *
     * @param list<string>      $headers
     * @param array<int, mixed> $values
     *
     * @return list<string|null>
     */
    private function positionalSample(array $headers, array $values): array
    {
        $row = [];
        foreach (array_keys($headers) as $i) {
            $value = $values[$i] ?? null;
            $string = \is_scalar($value) ? (string) $value : '';
            $row[] = '' === $string ? null : $string;
        }

        return $row;
    }

    private function parseCsv(string $absolutePath, ?FileEncoding $encodingOverride, ?string $delimiterOverride): ParsedFile
    {
        // IMP2-2.1 — stream from the path with an iconv read filter; detect
        // encoding + delimiter from a bounded prefix (no whole-file read).
        $prefix = $this->readPrefix($absolutePath);
        if ('' === trim($prefix)) {
            throw InvalidImportFileException::empty();
        }
        $encoding = $encodingOverride ?? $this->encodingDetector->detect($prefix);
        $delimiter = $delimiterOverride ?? $this->delimiterDetector->detect($this->encodingDetector->stripBom($prefix));

        $reader = Reader::from($absolutePath, 'r');
        if (FileEncoding::Utf8 !== $encoding && FileEncoding::Utf8Bom !== $encoding) {
            $reader->appendStreamFilterOnRead('convert.iconv.'.$encoding->iconvName().'/UTF-8//TRANSLIT');
        }
        $reader->setDelimiter($delimiter);

        // Read positionally (see ImportRowReader::iterateCsv): a header passed to
        // getRecords() trips league/csv's duplicate-column guard on repeated
        // blank columns that real exports carry (bosch-09-01-2026.csv).
        $headers = null;
        $sampleRows = [];
        $totalRows = 0;
        foreach ($reader->getRecords() as $record) {
            $values = array_values($record);
            if (null === $headers) {
                $headers = HeaderNormalizer::deduplicate(array_map(
                    static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '',
                    $values,
                ));
                if ([] === array_filter($headers, static fn (string $h): bool => '' !== $h)) {
                    throw InvalidImportFileException::noHeaderRow();
                }

                continue;
            }
            ++$totalRows;
            if (\count($sampleRows) < self::SAMPLE_ROWS) {
                $sampleRows[] = $this->positionalSample($headers, $values);
            }
        }

        if (null === $headers) {
            throw InvalidImportFileException::noHeaderRow();
        }

        return new ParsedFile(
            headers: $headers,
            sampleRows: $sampleRows,
            totalRows: $totalRows,
            encoding: $encoding,
            delimiter: $delimiter,
            sheetName: null,
            hadMultipleSheets: false,
        );
    }
}
