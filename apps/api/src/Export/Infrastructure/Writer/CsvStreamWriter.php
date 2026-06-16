<?php

declare(strict_types=1);

namespace App\Export\Infrastructure\Writer;

use App\Export\Domain\Enum\ExportEncoding;
use LogicException;
use RuntimeException;

/**
 * Native PHP CSV writer with encoding control.
 *
 * Two encodings (PRD §8.4):
 *   - UTF-8 with BOM (default) — Excel PL on Windows reads the BOM
 *     prefix and decodes UTF-8 correctly, so polskie znaki survive.
 *   - Windows-1250 — legacy Excel on Polish Windows without BOM
 *     support. Conversion done via iconv with `//TRANSLIT` so any
 *     character outside CP-1250 degrades to its closest match instead
 *     of corrupting the cell.
 *
 * Streaming CSV is trivial compared to XLSX — the file format is
 * plain text + delimiter, so we open the resource once and write
 * row by row.
 */
final class CsvStreamWriter implements RowWriter
{
    /** @var resource|null */
    private $handle;
    private ExportEncoding $encoding = ExportEncoding::Utf8Bom;

    public function open(string $path, ExportEncoding $encoding): void
    {
        if (null !== $this->handle) {
            throw new LogicException('CsvStreamWriter already opened.');
        }

        $handle = @fopen($path, 'w');
        if (false === $handle) {
            throw new RuntimeException(sprintf('Unable to open CSV target "%s" for writing.', $path));
        }

        $this->handle = $handle;
        $this->encoding = $encoding;

        if (ExportEncoding::Utf8Bom === $encoding) {
            fwrite($handle, "\xEF\xBB\xBF");
        }
    }

    /**
     * @param array<int, string> $headers
     */
    public function writeHeaders(array $headers): void
    {
        $this->writeRow($headers);
    }

    /**
     * @param array<int, string> $values
     */
    public function writeRow(array $values): void
    {
        $handle = $this->handle;
        if (null === $handle) {
            throw new LogicException('Open the writer before writing rows.');
        }

        // IMP2-2.8 (#1484) — CSV-injection defence (OWASP / GHSA-2xhg-w2g5-w95x):
        // a cell starting with a formula trigger gets a leading TAB so Excel /
        // Numbers render it as text instead of evaluating it. EXPORT ONLY — import
        // never mutates values. Applied before encoding conversion so the check
        // runs on the canonical UTF-8 string.
        $values = array_map([$this, 'neutraliseFormula'], $values);

        if (ExportEncoding::Windows1250 === $this->encoding) {
            $values = array_map([$this, 'toCp1250'], $values);
        }

        // Native fputcsv handles quoting / escaping. We force semicolon
        // for Excel PL friendliness — comma is the default but Excel PL
        // treats `,` as decimal separator and renders the whole row as
        // a single cell on import. Semicolon survives both Excel PL +
        // LibreOffice + Numbers reliably.
        fputcsv($handle, $values, separator: ';', enclosure: '"', escape: '\\');
    }

    public function close(): void
    {
        if (null === $this->handle) {
            return;
        }
        fflush($this->handle);
        fclose($this->handle);
        $this->handle = null;
    }

    private function neutraliseFormula(string $value): string
    {
        if ('' === $value) {
            return '';
        }

        return \in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "\t".$value
            : $value;
    }

    private function toCp1250(string $value): string
    {
        if ('' === $value) {
            return '';
        }
        $converted = @iconv('UTF-8', 'WINDOWS-1250//TRANSLIT', $value);

        return false === $converted ? $value : $converted;
    }
}
