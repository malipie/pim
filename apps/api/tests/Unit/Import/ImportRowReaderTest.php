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
}
