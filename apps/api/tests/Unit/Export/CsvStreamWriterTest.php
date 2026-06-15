<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export;

use App\Export\Domain\Enum\ExportEncoding;
use App\Export\Infrastructure\Writer\CsvStreamWriter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * IMP2-2.8 (#1484) — CSV-injection neutralisation on EXPORT (OWASP). A cell
 * starting with a formula trigger gets a leading TAB; cells that merely contain
 * such a character, or plain values, are untouched.
 */
final class CsvStreamWriterTest extends TestCase
{
    #[Test]
    public function neutralisesFormulaCellsWithLeadingTab(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pim-csv-');
        self::assertIsString($path);

        try {
            $writer = new CsvStreamWriter();
            $writer->open($path, ExportEncoding::Utf8Bom);
            $writer->writeRow(['=SUM(A1)', '+48123', '-5', '@cmd', 'safe', 'a=b']);
            $writer->close();
            $content = (string) file_get_contents($path);

            self::assertStringContainsString("\t=SUM(A1)", $content);
            self::assertStringContainsString("\t+48123", $content);
            self::assertStringContainsString("\t-5", $content);
            self::assertStringContainsString("\t@cmd", $content);

            // A value that only CONTAINS '=' (not leading) is left alone.
            self::assertStringContainsString('a=b', $content);
            self::assertStringNotContainsString("\ta=b", $content);

            // A plain value is left alone.
            self::assertStringContainsString('safe', $content);
            self::assertStringNotContainsString("\tsafe", $content);
        } finally {
            @unlink($path);
        }
    }
}
