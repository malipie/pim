<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Import\Application\Service\CompositeValueParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CompositeValueParserTest extends TestCase
{
    #[Test]
    public function parsesPriceWithCurrency(): void
    {
        $parser = new CompositeValueParser();

        self::assertSame(['amount' => 20.99, 'currency' => 'EUR'], $parser->parsePrice('20.99 EUR'));
        self::assertSame(['amount' => 21.99, 'currency' => 'USD'], $parser->parsePrice('21.99 USD'));
    }

    #[Test]
    public function parsesPriceWithCommaDecimalSeparator(): void
    {
        $parser = new CompositeValueParser();

        self::assertSame(['amount' => 20.99, 'currency' => 'PLN'], $parser->parsePrice('20,99 PLN'));
    }

    #[Test]
    public function parsesBarePriceWithoutCurrency(): void
    {
        $parser = new CompositeValueParser();

        self::assertSame(['amount' => 20.99], $parser->parsePrice('20.99'));
    }

    #[Test]
    public function uppercasesCurrencyToken(): void
    {
        $parser = new CompositeValueParser();

        self::assertSame(['amount' => 5.0, 'currency' => 'EUR'], $parser->parsePrice('5 eur'));
    }

    #[Test]
    public function parsesMetricWithUnit(): void
    {
        $parser = new CompositeValueParser();

        self::assertSame(['value' => 0.3, 'unit' => 'g'], $parser->parseMetric('0.3 g'));
        self::assertSame(['value' => 0.4, 'unit' => 'cm'], $parser->parseMetric('0.4 cm'));
    }

    #[Test]
    public function parsesBareMetricWithoutUnit(): void
    {
        $parser = new CompositeValueParser();

        self::assertSame(['value' => 11.0], $parser->parseMetric('11'));
    }

    #[Test]
    public function rejectsNonNumericLeadingToken(): void
    {
        $parser = new CompositeValueParser();

        self::assertNull($parser->parsePrice('not-a-number'));
        self::assertNull($parser->parseMetric('heavy'));
        self::assertNull($parser->parsePrice(''));
        self::assertNull($parser->parsePrice('EUR 20'));
    }

    #[Test]
    public function isNumericOrCompositeAcceptsNumbersAndComposites(): void
    {
        $parser = new CompositeValueParser();

        self::assertTrue($parser->isNumericOrComposite('20.99 EUR'));
        self::assertTrue($parser->isNumericOrComposite('0.3 g'));
        self::assertTrue($parser->isNumericOrComposite('100'));
        self::assertTrue($parser->isNumericOrComposite('20,99'));
        self::assertFalse($parser->isNumericOrComposite('not-a-number'));
        self::assertFalse($parser->isNumericOrComposite(''));
    }
}
