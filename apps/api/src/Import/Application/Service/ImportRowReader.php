<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Import\Domain\Enum\FileEncoding;
use App\Import\Domain\Exception\InvalidImportFileException;
use Generator;
use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;

use const PATHINFO_EXTENSION;

/**
 * Streams an uploaded CSV / xlsx file as `header → value` rows.
 *
 * Lives outside {@see ImportValidationService} because the async
 * {@see \App\Import\Application\Handler\ImportRunHandler} needs the
 * same iterator to drive persistence — sharing the reader keeps the
 * row numbering + encoding handling in one place.
 */
final readonly class ImportRowReader
{
    public function __construct(
        private EncodingDetector $encodingDetector,
        private DelimiterDetector $delimiterDetector,
    ) {
    }

    /**
     * Yields rows starting at `2` (header is row 1).
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
     * @return Generator<int, array<string, string|null>>
     */
    private function iterateXlsx(string $absolutePath): Generator
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($absolutePath);
        $sheet = $spreadsheet->getSheet(0);

        $headers = [];
        $rowNumber = 0;
        foreach ($sheet->getRowIterator() as $row) {
            ++$rowNumber;
            $cells = [];
            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getValue();
                $cells[] = null === $value ? null : (string) (\is_scalar($value) ? $value : '');
            }
            if (1 === $rowNumber) {
                $headers = array_map(static fn (?string $v): string => $v ?? '', $cells);
                continue;
            }
            $assoc = [];
            foreach ($headers as $index => $header) {
                $assoc[$header] = $cells[$index] ?? null;
            }
            yield $rowNumber - 1 => $assoc;
        }
    }

    /**
     * @return Generator<int, array<string, string|null>>
     */
    private function iterateCsv(string $absolutePath, ?FileEncoding $encodingOverride, ?string $delimiterOverride): Generator
    {
        $bytes = file_get_contents($absolutePath);
        if (false === $bytes) {
            throw InvalidImportFileException::unreadable($absolutePath);
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

        $rowNumber = 1;
        foreach ($reader->getRecords() as $record) {
            ++$rowNumber;
            $assoc = [];
            foreach ($record as $key => $value) {
                if (null === $value || '' === $value) {
                    $assoc[(string) $key] = null;
                } else {
                    $assoc[(string) $key] = \is_scalar($value) ? (string) $value : '';
                }
            }
            yield $rowNumber - 1 => $assoc;
        }
    }
}
