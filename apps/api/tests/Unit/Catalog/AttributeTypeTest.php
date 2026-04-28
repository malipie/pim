<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\AttributeType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AttributeTypeTest extends TestCase
{
    #[Test]
    public function tenCasesAreDefinedExactly(): void
    {
        // Spec is "10 typów" — guard against accidental case removal/addition.
        self::assertCount(10, AttributeType::cases());
    }

    #[Test]
    public function backingValuesAreLowercaseStrings(): void
    {
        // The DB stores strings; round-trip via `from()` must work and
        // values must be grep-friendly in fixtures + migrations.
        foreach (AttributeType::cases() as $case) {
            self::assertSame(strtolower($case->value), $case->value, $case->name);
            self::assertSame($case, AttributeType::from($case->value));
        }
    }

    /**
     * @return iterable<string, array{AttributeType, bool}>
     */
    public static function optionUsage(): iterable
    {
        yield 'text does not use options' => [AttributeType::Text, false];
        yield 'number does not use options' => [AttributeType::Number, false];
        yield 'select uses options' => [AttributeType::Select, true];
        yield 'multiselect uses options' => [AttributeType::Multiselect, true];
        yield 'date does not use options' => [AttributeType::Date, false];
        yield 'boolean does not use options' => [AttributeType::Boolean, false];
        yield 'asset does not use options' => [AttributeType::Asset, false];
        yield 'relation does not use options' => [AttributeType::Relation, false];
        yield 'price does not use options' => [AttributeType::Price, false];
        yield 'metric does not use options' => [AttributeType::Metric, false];
    }

    #[Test]
    #[DataProvider('optionUsage')]
    public function usesOptionsReportsTrueOnlyForSelectVariants(AttributeType $type, bool $expected): void
    {
        self::assertSame($expected, $type->usesOptions());
    }
}
