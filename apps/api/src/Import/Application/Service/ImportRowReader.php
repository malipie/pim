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
     * Maps cells by their column letter (`A`, `B`, …) rather than by the
     * iterator position. Exported XLSX files omit empty cells, so the
     * data row can jump `A2 → C2` (skipping a blank `parent_sku`). A
     * positional `headers[$i] => cells[$i]` mapping would then shift every
     * value left of the gap onto the wrong header. Keying both the header
     * row and each data row on the column coordinate keeps them aligned
     * regardless of which cells the file materialises (#1130).
     *
     * @return Generator<int, array<string, string|null>>
     */
    private function iterateXlsx(string $absolutePath): Generator
    {
        $reader = IOFactory::createReaderForFile($absolutePath);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($absolutePath);
        $sheet = $spreadsheet->getSheet(0);

        /** @var array<string, string> $headersByColumn column letter → header label */
        $headersByColumn = [];
        $rowNumber = 0;
        foreach ($sheet->getRowIterator() as $row) {
            ++$rowNumber;

            $cellsByColumn = [];
            foreach ($row->getCellIterator() as $cell) {
                $value = $cell->getValue();
                $cellsByColumn[$cell->getColumn()] = null === $value
                    ? null
                    : (string) (\is_scalar($value) ? $value : '');
            }

            if (1 === $rowNumber) {
                foreach ($cellsByColumn as $column => $label) {
                    if (null !== $label && '' !== $label) {
                        $headersByColumn[$column] = $label;
                    }
                }
                continue;
            }

            $assoc = [];
            foreach ($headersByColumn as $column => $header) {
                $assoc[$header] = $cellsByColumn[$column] ?? null;
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
