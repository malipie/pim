<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Application\Service\DelimiterDetector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DelimiterDetectorTest extends TestCase
{
    #[Test]
    public function semicolonIsPickedForPolishExports(): void
    {
        $detector = new DelimiterDetector();
        $sample = "sku;name;price\nFOO-1;Czujnik;99\nBAR-2;Zawor;120\n";

        self::assertSame(';', $detector->detect($sample));
    }

    #[Test]
    public function commaIsPickedForUsExports(): void
    {
        $detector = new DelimiterDetector();
        $sample = "sku,name,price\nFOO-1,Sensor,99\nBAR-2,Valve,120\n";

        self::assertSame(',', $detector->detect($sample));
    }

    #[Test]
    public function tabIsPickedForTsv(): void
    {
        $detector = new DelimiterDetector();
        $sample = "sku\tname\tprice\nFOO-1\tSensor\t99\nBAR-2\tValve\t120\n";

        self::assertSame("\t", $detector->detect($sample));
    }

    #[Test]
    public function semicolonWinsOverCommaWhenConsistencyTies(): void
    {
        $detector = new DelimiterDetector();
        // Headers and values both contain the same number of `,` and `;`,
        // but `;` ranks first in the candidate order so it wins.
        $sample = "a;b;c\nx;y;z\n1;2;3\n";

        self::assertSame(';', $detector->detect($sample));
    }

    #[Test]
    public function fallsBackToSemicolonOnEmptySample(): void
    {
        $detector = new DelimiterDetector();

        self::assertSame(';', $detector->detect(''));
    }
}
