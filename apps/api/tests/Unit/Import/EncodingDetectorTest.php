<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Application\Service\EncodingDetector;
use App\Import\Domain\Enum\FileEncoding;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EncodingDetectorTest extends TestCase
{
    #[Test]
    public function utf8BomIsRecognised(): void
    {
        $detector = new EncodingDetector();
        $bytes = "\xEF\xBB\xBFsku;name\nABC-1;Czujnik\n";

        self::assertSame(FileEncoding::Utf8Bom, $detector->detect($bytes));
    }

    #[Test]
    public function plainUtf8IsRecognised(): void
    {
        $detector = new EncodingDetector();
        $bytes = "sku;name\nABC-1;Czujnik ciśnienia\n";

        self::assertSame(FileEncoding::Utf8, $detector->detect($bytes));
    }

    #[Test]
    public function windows1250IsRecognisedFromPolishBytes(): void
    {
        $detector = new EncodingDetector();
        $cp1250 = iconv('UTF-8', 'Windows-1250', "sku;name\nABC-1;Czujnik ciśnienia\n");
        self::assertNotFalse($cp1250);

        self::assertSame(FileEncoding::Windows1250, $detector->detect($cp1250));
    }

    #[Test]
    public function bomIsStrippedFromBytes(): void
    {
        $detector = new EncodingDetector();
        $withBom = "\xEF\xBB\xBFhello";

        self::assertSame('hello', $detector->stripBom($withBom));
    }

    #[Test]
    public function stripBomLeavesPlainBytesAlone(): void
    {
        $detector = new EncodingDetector();
        self::assertSame('hello', $detector->stripBom('hello'));
    }
}
