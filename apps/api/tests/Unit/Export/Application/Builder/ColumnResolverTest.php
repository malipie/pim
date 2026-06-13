<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export\Application\Builder;

use App\Export\Application\Builder\ColumnDefinition;
use App\Export\Application\Builder\ColumnResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * EXP-03 (#582) — ColumnResolver contract tests.
 *
 * Parses `selected_columns` strings into a structured list the builder
 * can iterate without per-row reparsing. Round-trip stability: every
 * input key survives as `ColumnDefinition::$key` so the XLSX header
 * matches the column key the reimport expects (IMP-19 #605 multi-locale
 * notation pairs with this resolver).
 */
final class ColumnResolverTest extends TestCase
{
    #[Test]
    public function recognisesBuiltInKeys(): void
    {
        $resolver = new ColumnResolver();
        foreach (['sku', 'parent_sku', 'category', 'status', 'enabled', 'completeness_pct', 'created_at', 'updated_at'] as $key) {
            $col = $resolver->resolveOne($key);
            self::assertTrue($col->isBuiltIn(), $key.' should resolve as built-in');
            self::assertSame($key, $col->key);
            self::assertSame($key, $col->code);
        }
    }

    #[Test]
    public function bareAttributeResolvesWithNoLocale(): void
    {
        $col = new ColumnResolver()->resolveOne('description');
        self::assertTrue($col->isAttribute());
        self::assertSame('description', $col->code);
        self::assertNull($col->locale);
        self::assertNull($col->channel);
    }

    #[Test]
    public function attributeDotLocaleSplitsCorrectly(): void
    {
        // PRD §6.1 — locale-scopable column. Pairs with IMP-19 (#605) for
        // round-trip header reimport.
        $col = new ColumnResolver()->resolveOne('description.pl');
        self::assertTrue($col->isAttribute());
        self::assertSame('description', $col->code);
        self::assertSame('pl', $col->locale);
    }

    #[Test]
    public function modifierMatchingASessionChannelResolvesAsChannel(): void
    {
        // #1229 — a modifier naming one of the session's channels is a
        // channel-scoped column, not a locale.
        $col = new ColumnResolver()->resolveOne('description.shopify', ['shopify', 'baselinker']);
        self::assertTrue($col->isAttribute());
        self::assertSame('description', $col->code);
        self::assertSame('shopify', $col->channel);
        self::assertNull($col->locale);
    }

    #[Test]
    public function modifierNotAmongSessionChannelsStaysLocale(): void
    {
        // #1229 — disambiguation by membership: `pl` is not a channel, so it
        // stays a locale even when channels are present.
        $col = new ColumnResolver()->resolveOne('description.pl', ['shopify']);
        self::assertSame('pl', $col->locale);
        self::assertNull($col->channel);
    }

    #[Test]
    public function combinedLocaleChannelNotationResolvesBothSegments(): void
    {
        // IMP2-1.6 (#1469) — `description.pl.shopify`: fixed order, last
        // segment is the channel (must be a session channel), middle is the
        // locale.
        $col = new ColumnResolver()->resolveOne('description.pl.shopify', ['shopify', 'baselinker']);
        self::assertTrue($col->isAttribute());
        self::assertSame('description', $col->code);
        self::assertSame('pl', $col->locale);
        self::assertSame('shopify', $col->channel);
    }

    #[Test]
    public function combinedNotationWithNonChannelLastSegmentDegradesToLocale(): void
    {
        // IMP2-1.6 — when the last segment is not a session channel the key
        // is not the combined notation; it degrades to a locale-only column
        // (blank cell via the value-index miss), never a misread channel.
        $col = new ColumnResolver()->resolveOne('description.pl.unknown', ['shopify']);
        self::assertSame('description', $col->code);
        self::assertSame('pl.unknown', $col->locale);
        self::assertNull($col->channel);
    }

    #[Test]
    public function resolveArrayReturnsDefinitionsInOrder(): void
    {
        $resolver = new ColumnResolver();
        $resolved = $resolver->resolve(['sku', 'name', 'description.pl', 'description.en', 'parent_sku']);

        self::assertCount(5, $resolved);
        self::assertSame(['sku', 'name', 'description.pl', 'description.en', 'parent_sku'], array_map(static fn (ColumnDefinition $c): string => $c->key, $resolved));
        self::assertTrue($resolved[0]->isBuiltIn());      // sku
        self::assertTrue($resolved[1]->isAttribute());    // name (treated as attribute reference)
        self::assertSame('pl', $resolved[2]->locale);
        self::assertSame('en', $resolved[3]->locale);
        self::assertTrue($resolved[4]->isBuiltIn());      // parent_sku
    }

    #[Test]
    public function unknownColumnKeyResolvesAsAttributeSoStaleProfilesDegradeGracefully(): void
    {
        // R-47 (PRD §14) — profile with deleted attribute should not 500.
        // Builder turns these into blank cells via the value index miss.
        $col = new ColumnResolver()->resolveOne('definitely_not_an_attribute');
        self::assertTrue($col->isAttribute());
        self::assertNull($col->locale);
    }
}
