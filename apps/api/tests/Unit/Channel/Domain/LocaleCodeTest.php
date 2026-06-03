<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel\Domain;

use App\Channel\Domain\LocaleCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LocaleCodeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function shortCases(): iterable
    {
        yield 'bcp-47 underscore' => ['pl_PL', 'pl'];
        yield 'bcp-47 hyphen' => ['en-US', 'en'];
        yield 'already short' => ['pl', 'pl'];
        yield 'uppercase short' => ['PL', 'pl'];
        yield 'uppercase language with region' => ['DE_DE', 'de'];
        yield 'trailing separator' => ['pl_', 'pl'];
    }

    #[Test]
    #[DataProvider('shortCases')]
    public function toShortStripsAndLowercasesTheRegion(string $code, string $expected): void
    {
        self::assertSame($expected, LocaleCode::toShort($code));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function regionCases(): iterable
    {
        yield 'underscore region' => ['pl_PL', 'PL'];
        yield 'hyphen lowercase region' => ['en-us', 'US'];
        yield 'language only' => ['pl', null];
        yield 'empty region' => ['pl_', null];
    }

    #[Test]
    #[DataProvider('regionCases')]
    public function regionIsUppercasedOrNull(string $code, ?string $expected): void
    {
        self::assertSame($expected, LocaleCode::region($code));
    }

    #[Test]
    public function hasRegionReflectsPresenceOfARegionSubtag(): void
    {
        self::assertTrue(LocaleCode::hasRegion('pl_PL'));
        self::assertTrue(LocaleCode::hasRegion('en-US'));
        self::assertFalse(LocaleCode::hasRegion('pl'));
        self::assertFalse(LocaleCode::hasRegion('pl_'));
    }
}
