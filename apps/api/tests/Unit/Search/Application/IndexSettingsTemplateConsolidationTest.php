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
        self::assertCount(4, $kinds);
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
        // category `path` stays — it is intrinsic category data, not a
        // user-attribute. seo_title / alt_text / caption were dropped when
        // Asset / Category became closed system kinds (amends ADR-009).
        self::assertContains('path', $searchable);
        self::assertNotContains('seo_title', $searchable);
        self::assertNotContains('alt_text', $searchable);
        self::assertNotContains('caption', $searchable);

        $sortable = $settings['sortableAttributes'];
        self::assertIsArray($sortable);
        self::assertContains('createdAt', $sortable);
        self::assertContains('price', $sortable);
    }
}
