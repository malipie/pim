<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Import\Domain\Enum\FileEncoding;
use App\Import\Domain\Exception\InvalidImportFileException;
use App\Import\Domain\ValueObject\ParsedFile;
use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
        try {
            $reader = IOFactory::createReaderForFile($absolutePath);
        } catch (Throwable $exception) {
            throw InvalidImportFileException::corrupted($absolutePath, $exception);
        }

        // Cell formula evaluation is OFF (spec §10): cached values only.
        $reader->setReadDataOnly(true);

        $spreadsheet = $reader->load($absolutePath);
        $sheetNames = $spreadsheet->getSheetNames();
        $hadMultipleSheets = \count($sheetNames) > 1;
        $sheet = $spreadsheet->getSheet(0);
        $sheetName = $sheet->getTitle();

        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getValue();
                $cells[] = null === $value ? null : (string) (\is_scalar($value) ? $value : '');
            }
            $rows[] = $cells;
        }

        if ([] === $rows || array_filter($rows[0], static fn ($v): bool => null !== $v && '' !== $v) === []) {
            throw InvalidImportFileException::noHeaderRow();
        }

        $headers = array_map(static fn (?string $value): string => $value ?? '', $rows[0]);
        $bodyRows = \array_slice($rows, 1);
        $sampleRows = \array_slice($bodyRows, 0, self::SAMPLE_ROWS);

        return new ParsedFile(
            headers: $headers,
            sampleRows: $sampleRows,
            totalRows: \count($bodyRows),
            encoding: FileEncoding::Utf8,
            delimiter: null,
            sheetName: $sheetName,
            hadMultipleSheets: $hadMultipleSheets,
        );
    }

    private function parseCsv(string $absolutePath, ?FileEncoding $encodingOverride, ?string $delimiterOverride): ParsedFile
    {
        $bytes = file_get_contents($absolutePath);
        if (false === $bytes) {
            throw InvalidImportFileException::unreadable($absolutePath);
        }
        if ('' === trim($bytes)) {
            throw InvalidImportFileException::empty();
        }

        $encoding = $encodingOverride ?? $this->encodingDetector->detect($bytes);
        $body = $this->encodingDetector->stripBom($bytes);
        if (FileEncoding::Utf8 !== $encoding && FileEncoding::Utf8Bom !== $encoding) {
            $converted = @iconv($encoding->iconvName(), 'UTF-8//TRANSLIT', $body);
            if (false === $converted) {
                throw InvalidImportFileException::corruptedEncoding($encoding->value);
            }
            $body = $converted;
        }

        $delimiter = $delimiterOverride ?? $this->delimiterDetector->detect(substr($body, 0, 4096));

        $reader = Reader::fromString($body);
        $reader->setDelimiter($delimiter);
        $reader->setHeaderOffset(0);

        $headers = $reader->getHeader();
        if ([] === $headers) {
            throw InvalidImportFileException::noHeaderRow();
        }

        $sampleRows = [];
        $totalRows = 0;
        foreach ($reader->getRecords() as $record) {
            ++$totalRows;
            if (\count($sampleRows) < self::SAMPLE_ROWS) {
                $row = [];
                foreach ($headers as $header) {
                    $value = $record[$header] ?? null;
                    if (null === $value || '' === $value) {
                        $row[] = null;
                    } else {
                        $row[] = \is_scalar($value) ? (string) $value : '';
                    }
                }
                $sampleRows[] = $row;
            }
        }

        return new ParsedFile(
            headers: array_values($headers),
            sampleRows: $sampleRows,
            totalRows: $totalRows,
            encoding: $encoding,
            delimiter: $delimiter,
            sheetName: null,
            hadMultipleSheets: false,
        );
    }
}
