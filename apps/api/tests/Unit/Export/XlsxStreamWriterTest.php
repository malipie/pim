<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export;

use App\Export\Infrastructure\Writer\XlsxStreamWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * IMP2-2.8 (#1484) follow-up — CSV/Formula-injection neutralisation on the XLSX
 * export path. OpenSpout's Cell::fromValue() promotes a string with a leading
 * '=' to an active FormulaCell, so without neutralisation a cell like
 * '=HYPERLINK(...)' is written as a live formula (<f> element) that executes when
 * opened in Excel/Numbers. The writer must prefix a TAB to formula-trigger cells
 * so the worksheet contains only inline string cells, never a <f> element.
 */
final class XlsxStreamWriterTest extends TestCase
{
    #[Test]
    public function neutralisesFormulaCellsAndNeverEmitsFormulaElement(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pim-xlsx-');
        self::assertIsString($path);

        try {
            $writer = new XlsxStreamWriter();
            $writer->openToFile($path);
            $writer->writeHeaders(['sku', 'name']);
            $writer->writeRow(['=HYPERLINK("http://evil","x")', 'safe']);
            $writer->writeRow(['+48123', 'a=b']);
            $writer->writeRow(['-5', '@cmd']);
            $writer->close();

            $zip = new ZipArchive();
            self::assertTrue(true === $zip->open($path));
            $xml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();

            // 1) The actual injection vector: no active formula element at all.
            self::assertStringNotContainsString('<f>', $xml);

            // 2) Formula-trigger cells are TAB-prefixed inline strings.
            self::assertStringContainsString("<t>\t=HYPERLINK(", $xml);
            self::assertStringContainsString("<t>\t+48123</t>", $xml);
            self::assertStringContainsString("<t>\t-5</t>", $xml);
            self::assertStringContainsString("<t>\t@cmd</t>", $xml);

            // 3) Safe values and a non-leading '=' are left untouched.
            self::assertStringContainsString('<t>safe</t>', $xml);
            self::assertStringContainsString('<t>a=b</t>', $xml);
            self::assertStringNotContainsString("\tsafe", $xml);
            self::assertStringNotContainsString("\ta=b", $xml);
        } finally {
            @unlink($path);
        }
    }
}
