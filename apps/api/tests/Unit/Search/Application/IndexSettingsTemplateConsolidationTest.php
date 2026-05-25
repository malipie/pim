<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Application;

use App\Catalog\Domain\ObjectKind;
use App\Search\Application\IndexSettingsTemplate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * ULV-02 (#983) — covers the per-kind → consolidated `objects` index
 * cutover: every `indexName()` call resolves to the universal index,
 * `settingsFor()` returns one merged settings payload, and custom kinds
 * are now included in `indexedKinds()`.
 */
final class IndexSettingsTemplateConsolidationTest extends TestCase
{
    #[Test]
    public function indexNameAlwaysReturnsUniversalIndexRegardlessOfKind(): void
    {
        self::assertSame('objects', IndexSettingsTemplate::indexName());
        self::assertSame('objects', IndexSettingsTemplate::indexName(ObjectKind::Product));
        self::assertSame('objects', IndexSettingsTemplate::indexName(ObjectKind::Category));
        self::assertSame('objects', IndexSettingsTemplate::indexName(ObjectKind::Asset));
        self::assertSame('objects', IndexSettingsTemplate::indexName(ObjectKind::Brand));
        self::assertSame('objects', IndexSettingsTemplate::indexName(ObjectKind::Custom));
    }

    #[Test]
    public function indexedKindsIncludesCustomPostConsolidation(): void
    {
        $kinds = IndexSettingsTemplate::indexedKinds();

        self::assertContains(ObjectKind::Custom, $kinds);
        self::assertContains(ObjectKind::Product, $kinds);
        self::assertContains(ObjectKind::Category, $kinds);
        self::assertContains(ObjectKind::Asset, $kinds);
        self::assertContains(ObjectKind::Brand, $kinds);
        self::assertCount(5, $kinds);
    }

    #[Test]
    public function settingsForReturnsUnionPayloadIndependentOfKindArgument(): void
    {
        $template = new IndexSettingsTemplate();

        $a = $template->settingsFor();
        $b = $template->settingsFor(ObjectKind::Product);
        $c = $template->settingsFor(ObjectKind::Custom);

        self::assertSame($a, $b, 'settings must be identical regardless of kind hint');
        self::assertSame($a, $c);

        self::assertArrayHasKey('filterableAttributes', $a);
        $filterable = $a['filterableAttributes'];
        self::assertIsArray($filterable);
        self::assertContains('tenantId', $filterable, 'tenant isolation filter is mandatory');
        self::assertContains('objectTypeId', $filterable, 'ULV scope filter is mandatory');
        self::assertContains('kind', $filterable, 'BC kind filter retained');
    }

    #[Test]
    public function searchableAndSortableAreUnionOfPreUlvKinds(): void
    {
        $settings = new IndexSettingsTemplate()->settingsFor();

        $searchable = $settings['searchableAttributes'];
        self::assertIsArray($searchable);
        // pre-ULV product searchable
        self::assertContains('code', $searchable);
        self::assertContains('name', $searchable);
        self::assertContains('sku', $searchable);
        // pre-ULV category searchable
        self::assertContains('path', $searchable);
        self::assertContains('seo_title', $searchable);
        // pre-ULV asset searchable
        self::assertContains('alt_text', $searchable);
        self::assertContains('caption', $searchable);

        $sortable = $settings['sortableAttributes'];
        self::assertIsArray($sortable);
        self::assertContains('createdAt', $sortable);
        self::assertContains('price', $sortable);
    }
}
