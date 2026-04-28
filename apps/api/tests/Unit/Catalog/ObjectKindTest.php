<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\ObjectKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ObjectKindTest extends TestCase
{
    #[Test]
    public function fourCasesAreDefinedExactly(): void
    {
        // ADR-009 fixes the kind enum at four values; guard against drift.
        self::assertCount(4, ObjectKind::cases());
    }

    #[Test]
    public function backingValuesAreLowercaseStrings(): void
    {
        foreach (ObjectKind::cases() as $case) {
            self::assertSame(strtolower($case->value), $case->value, $case->name);
            self::assertSame($case, ObjectKind::from($case->value));
        }
    }

    /**
     * @return iterable<string, array{ObjectKind, bool}>
     */
    public static function builtInMatrix(): iterable
    {
        yield 'product is built-in' => [ObjectKind::Product, true];
        yield 'category is built-in' => [ObjectKind::Category, true];
        yield 'asset is built-in' => [ObjectKind::Asset, true];
        yield 'custom is NOT built-in' => [ObjectKind::Custom, false];
    }

    #[Test]
    #[DataProvider('builtInMatrix')]
    public function isBuiltInIsTrueForEverythingExceptCustom(ObjectKind $kind, bool $expected): void
    {
        self::assertSame($expected, $kind->isBuiltIn());
    }
}
