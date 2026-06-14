<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Application\Service\DelimiterDetector;
use App\Import\Application\Service\EncodingDetector;
use App\Import\Application\Service\ImportRowReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportRowReaderTest extends TestCase
{
    #[Test]
    public function xlsxRowsMapByColumnCoordinateNotPosition(): void
    {
        // Header skips column B (the exporter omits empty cells). A naive
        // positional reader would shift `name` onto the blank header and
        // leak the B2 cell; coordinate mapping keeps each value under its
        // own header (#1130).
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'sku');
        // B1 intentionally left blank.
        $sheet->setCellValue('C1', 'name');
        $sheet->setCellValue('D1', 'price');

        $sheet->setCellValue('A2', 'RT-1');
        $sheet->setCellValue('B2', 'leaked-if-positional');
        $sheet->setCellValue('C2', 'Buty');
        $sheet->setCellValue('D2', '20.99 EUR');

        $path = tempnam(sys_get_temp_dir(), 'imp-reader-unit-').'.xlsx';
        new XlsxWriter($spreadsheet)->save($path);

        try {
            $reader = new ImportRowReader(new EncodingDetector(), new DelimiterDetector());
            $rows = iterator_to_array($reader->read($path));

            self::assertCount(1, $rows);
            $row = $rows[1];
            self::assertSame('RT-1', $row['sku']);
            self::assertSame('Buty', $row['name']);
            self::assertSame('20.99 EUR', $row['price']);
            self::assertArrayNotHasKey('', $row, 'Blank header column must not produce a key.');
            self::assertNotContains('leaked-if-positional', $row, 'B2 has no header — its value must not leak.');
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function csvDuplicateHeadersAreNotLost(): void
    {
        // IMP2-2.1 (D12) — a repeated column header must not silently overwrite
        // its twin; the second occurrence becomes `color#2`.
        $path = tempnam(sys_get_temp_dir(), 'imp-dup-').'.csv';
        file_put_contents($path, "sku;color;size;color\nS1;red;M;blue\n");

        try {
            $reader = new ImportRowReader(new EncodingDetector(), new DelimiterDetector());
            $rows = iterator_to_array($reader->read($path));

            self::assertSame('red', $rows[1]['color']);
            self::assertSame('blue', $rows[1]['color#2'], 'duplicate header preserved, not overwritten');
            self::assertSame('M', $rows[1]['size']);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function csvBlankAndDuplicateHeadersDoNotThrowAndPreserveColumns(): void
    {
        // IMP2-2.1 — real exports (bosch-09-01-2026.csv) carry both repeated AND
        // empty header cells. league/csv rejects a header array with repeated ''
        // as "duplicate column names", so the reader must map positionally.
        // Blank-header columns are dropped from the assoc; the duplicate `color`
        // survives as `color#2`.
        $path = tempnam(sys_get_temp_dir(), 'imp-blank-').'.csv';
        file_put_contents($path, "sku;;color;;color\nS1;skip-a;red;skip-b;blue\n");

        try {
            $reader = new ImportRowReader(new EncodingDetector(), new DelimiterDetector());
            $rows = iterator_to_array($reader->read($path));

            self::assertCount(1, $rows);
            $row = $rows[1];
            self::assertSame('S1', $row['sku']);
            self::assertSame('red', $row['color']);
            self::assertSame('blue', $row['color#2'], 'duplicate header preserved by position');
            self::assertArrayNotHasKey('', $row, 'blank-header columns produce no key');
            self::assertNotContains('skip-a', $row, 'value under a blank header must not leak');
            self::assertNotContains('skip-b', $row);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function xlsxNumericCellSurfacesAsStringWithoutDotZero(): void
    {
        // IMP2-2.1 — a numeric EAN cell must come back as a plain string, not
        // `5901234123457.0` (Akeneo PIM-10167 lesson).
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('A1', 'sku');
        $sheet->setCellValue('B1', 'ean');
        $sheet->setCellValue('A2', 'P-1');
        $sheet->setCellValue('B2', 5901234123457);

        $path = tempnam(sys_get_temp_dir(), 'imp-ean-').'.xlsx';
        new XlsxWriter($spreadsheet)->save($path);

        try {
            $reader = new ImportRowReader(new EncodingDetector(), new DelimiterDetector());
            $rows = iterator_to_array($reader->read($path));

            self::assertSame('5901234123457', $rows[1]['ean']);
            self::assertStringNotContainsString('.0', $rows[1]['ean']);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function csvReadingIsConstantMemoryRegardlessOfSize(): void
    {
        // IMP2-2.1 — streaming: peak memory reading a 100k-row CSV stays far
        // below the file footprint (the old file_get_contents path held the
        // whole blob + a converted copy).
        $path = tempnam(sys_get_temp_dir(), 'imp-mem-').'.csv';
        $handle = fopen($path, 'w');
        self::assertIsResource($handle);
        fwrite($handle, "sku;name;price\n");
        for ($i = 0; $i < 100_000; ++$i) {
            fwrite($handle, \sprintf("SKU-%06d;Product name %d;%d.99\n", $i, $i, $i % 1000));
        }
        fclose($handle);

        try {
            $reader = new ImportRowReader(new EncodingDetector(), new DelimiterDetector());
            // Measure the peak DELTA the read forces over the pre-existing
            // baseline, not the absolute peak: memory_get_peak_usage(true)
            // reports the OS arena high-water, which in the full suite is
            // already ~100 MiB from earlier tests and never shrinks. Only the
            // delta isolates this reader's footprint. Streaming → near-zero;
            // accumulating all 100k assoc rows would add ~50 MiB.
            gc_collect_cycles();
            $baseline = memory_get_usage(true);
            memory_reset_peak_usage();
            $count = 0;
            foreach ($reader->read($path) as $row) {
                ++$count; // consume without accumulating
                unset($row);
            }

            self::assertSame(100_000, $count);
            $peakDeltaMib = (memory_get_peak_usage(true) - $baseline) / (1024 * 1024);
            self::assertLessThan(
                24,
                $peakDeltaMib,
                \sprintf('reader added %.1f MiB over baseline — not streaming (100k rows accumulated ≈ 50 MiB)', $peakDeltaMib),
            );
        } finally {
            @unlink($path);
        }
    }
}
