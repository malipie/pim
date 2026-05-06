<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Application\Service\DelimiterDetector;
use App\Import\Application\Service\EncodingDetector;
use App\Import\Application\Service\FileParserService;
use App\Import\Domain\Enum\FileEncoding;
use App\Import\Domain\Exception\InvalidImportFileException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileParserServiceTest extends TestCase
{
    #[Test]
    public function parsesTheFestoCsvFixtureWith15HeadersAnd3SampleRows(): void
    {
        $service = $this->parser();
        $path = __DIR__.'/../../fixtures/imports/festo-q2-2026.csv';

        $parsed = $service->parse($path);

        self::assertCount(15, $parsed->headers);
        self::assertSame('Kod produktu', $parsed->headers[0]);
        self::assertSame('Notatki wewn.', $parsed->headers[14]);
        self::assertSame(3, $parsed->totalRows);
        self::assertCount(3, $parsed->sampleRows);
        self::assertSame(';', $parsed->delimiter);
        self::assertSame(FileEncoding::Utf8, $parsed->encoding);
    }

    #[Test]
    public function detectsWindows1250EncodingFromGeneratedFile(): void
    {
        $service = $this->parser();
        $contents = "sku;name\nABC-1;Czujnik ciśnienia\n";
        $cp1250 = iconv('UTF-8', 'Windows-1250', $contents);
        self::assertNotFalse($cp1250);

        $path = $this->writeTempFile($cp1250, '.csv');
        try {
            $parsed = $service->parse($path);
            self::assertSame(FileEncoding::Windows1250, $parsed->encoding);
            self::assertSame('Czujnik ciśnienia', $parsed->sampleRows[0][1]);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function utf8BomIsStrippedFromHeaders(): void
    {
        $service = $this->parser();
        $path = $this->writeTempFile("\xEF\xBB\xBFsku;name\nFOO-1;Sensor\n", '.csv');
        try {
            $parsed = $service->parse($path);
            self::assertSame(['sku', 'name'], $parsed->headers);
            self::assertSame(FileEncoding::Utf8Bom, $parsed->encoding);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function rejectsEmptyCsvFile(): void
    {
        $service = $this->parser();
        $path = $this->writeTempFile('', '.csv');

        try {
            $this->expectException(InvalidImportFileException::class);
            $service->parse($path);
        } finally {
            @unlink($path);
        }
    }

    #[Test]
    public function rejectsUnsupportedExtension(): void
    {
        $service = $this->parser();
        $path = $this->writeTempFile('whatever', '.xls');

        try {
            $this->expectException(InvalidImportFileException::class);
            $this->expectExceptionMessageMatches('/Unsupported import file extension/');
            $service->parse($path);
        } finally {
            @unlink($path);
        }
    }

    private function parser(): FileParserService
    {
        return new FileParserService(new EncodingDetector(), new DelimiterDetector());
    }

    private function writeTempFile(string $contents, string $suffix): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imp-test-');
        self::assertNotFalse($path);
        $renamed = $path.$suffix;
        rename($path, $renamed);
        file_put_contents($renamed, $contents);

        return $renamed;
    }
}
