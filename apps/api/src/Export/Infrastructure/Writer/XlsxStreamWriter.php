<?php

declare(strict_types=1);

namespace App\Export\Infrastructure\Writer;

use LogicException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Options;
use OpenSpout\Writer\XLSX\Writer;

/**
 * OpenSpout XLSX writer wired for streaming + low-memory output.
 *
 * Why OpenSpout: it streams XLSX (writes one row at a time, never builds
 * the whole spreadsheet in memory). PRD §11.2 needs <30s for 50k SKU on
 * a FrankenPHP worker with a 50 MB memory budget — PhpSpreadsheet et al.
 * cannot fit that envelope (CLAUDE.md §3.10).
 *
 * Wrap class so the controller / async handler stays decoupled from the
 * library and we can swap implementations (or pin a different version)
 * without touching call sites.
 *
 * XLSX is a zipped container so we cannot pipe it directly to HTTP
 * output mid-stream. Instead we write to a tempfile and let the caller
 * stream the completed file. Sub-100-row sync exports keep the file in
 * memory-mapped tmp (fast); the async handler (EXP-06) writes to disk +
 * uploads to MinIO.
 */
final class XlsxStreamWriter implements RowWriter
{
    private Writer $writer;
    private bool $headersWritten = false;
    private bool $opened = false;

    public function __construct()
    {
        // Inline strings (`SHOULD_USE_INLINE_STRINGS` = true by default in
        // OpenSpout 5.x Options) keep the row stream tight — no
        // shared-string table that would have to live in memory until
        // close().
        $this->writer = new Writer(new Options());
    }

    /**
     * Open the writer pointing at a filesystem path. For the sync HTTP
     * path the controller passes a tempfile, then streams its bytes to
     * the response. The async path passes the MinIO local stage path.
     */
    public function openToFile(string $path): void
    {
        if ($this->opened) {
            throw new LogicException('XlsxStreamWriter already opened.');
        }
        $this->writer->openToFile($path);
        $this->opened = true;
    }

    /**
     * Write headers (column keys) as the first row, bolded.
     *
     * @param array<int, string> $headers
     */
    public function writeHeaders(array $headers): void
    {
        $this->guardOpen();
        if ($this->headersWritten) {
            throw new LogicException('Headers already written for this XLSX stream.');
        }

        $style = new Style()->withFontBold(true);
        $this->writer->addRow(Row::fromValuesWithStyle($headers, $style));
        $this->headersWritten = true;
    }

    /**
     * Write one data row. Values must already be ordered to match the
     * column keys passed to {@see writeHeaders()}.
     *
     * @param array<int, string> $values
     */
    public function writeRow(array $values): void
    {
        $this->guardOpen();
        $this->writer->addRow(Row::fromValues($values));
    }

    /**
     * Close + flush the XLSX file. Idempotent.
     */
    public function close(): void
    {
        if (!$this->opened) {
            return;
        }
        $this->writer->close();
        $this->opened = false;
    }

    private function guardOpen(): void
    {
        if (!$this->opened) {
            throw new LogicException('Open the writer before writing rows.');
        }
    }
}
