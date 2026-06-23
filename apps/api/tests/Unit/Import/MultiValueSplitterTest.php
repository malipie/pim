<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Application\Service\MultiValueSplitter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MultiValueSplitterTest extends TestCase
{
    #[Test]
    public function splitsPipeListForBackwardCompat(): void
    {
        self::assertSame(['a', 'b', 'c'], MultiValueSplitter::split('a|b|c'));
    }

    #[Test]
    public function splitsNewlineListFromExternalExports(): void
    {
        // IdoSell/IAI packs `36\n37\n38\n39\n40\n41` into one quoted CSV cell.
        self::assertSame(
            ['36', '37', '38', '39', '40', '41'],
            MultiValueSplitter::split("36\n37\n38\n39\n40\n41"),
        );
    }

    #[Test]
    public function splitsMixedPipeAndNewline(): void
    {
        self::assertSame(['a', 'b', 'c'], MultiValueSplitter::split("a|b\nc"));
    }

    #[Test]
    public function splitsCarriageReturnNewline(): void
    {
        self::assertSame(['a', 'b'], MultiValueSplitter::split("a\r\nb"));
    }

    #[Test]
    public function trimsTokensAndDropsEmpties(): void
    {
        self::assertSame(['a', 'b'], MultiValueSplitter::split("a| \n |b\n"));
    }

    #[Test]
    public function singleValueStaysSingle(): void
    {
        self::assertSame(['Beżowy'], MultiValueSplitter::split('Beżowy'));
    }

    #[Test]
    public function emptyCellYieldsEmptyList(): void
    {
        self::assertSame([], MultiValueSplitter::split(''));
        self::assertSame([], MultiValueSplitter::split("\n|\n"));
    }
}
