<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export\Application\Builder;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectCategoryRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Channel\Contracts\ChannelResolverInterface;
use App\Export\Application\Builder\ColumnResolver;
use App\Export\Application\Builder\ExportBuilder;
use App\Export\Application\Builder\ValueSerializer;
use App\Export\Domain\Entity\ExportSession;
use App\Export\Domain\Enum\ExportFormat;
use App\Export\Domain\Enum\ExportSource;
use App\Export\Domain\Enum\ExportTargetScope;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * EXP-03 (#582) — ExportBuilder contract tests.
 *
 * Exercises the full per-row rendering pipeline with mocked repositories
 * and real ColumnResolver + ValueSerializer. Covers PRD §8 contracts
 * (variants flat, multi-locale columns, blank cells, pipe-multivalue,
 * stale-attribute degradation).
 */
final class ExportBuilderTest extends TestCase
{
    #[Test]
    public function buildThrowsWhenSessionHasNoTenant(): void
    {
        $builder = $this->newBuilder([], []);
        $session = $this->newSession(['sku']);

        // Session as constructed has tenant=null until assignTenant() is
        // called — the persistence listener owns that in production.
        $this->expectException(LogicException::class);
        iterator_to_array($builder->build([$this->newProduct('TST-001')], $session));
    }

    #[Test]
    public function rendersBuiltInSkuAndParentSkuColumns(): void
    {
        $master = $this->newProduct('MST-001');
        $variant = $this->newProduct('VAR-001', $master);

        $builder = $this->newBuilder(
            valuesByObject: [],
            categoriesByObject: [],
        );

        $session = $this->newSessionWithTenant(['sku', 'parent_sku']);
        $rows = iterator_to_array($builder->build([$master, $variant], $session));

        self::assertCount(2, $rows);
        self::assertSame(['sku' => 'MST-001', 'parent_sku' => ''], $rows[0]);
        self::assertSame(['sku' => 'VAR-001', 'parent_sku' => 'MST-001'], $rows[1]);
    }

    #[Test]
    public function rendersAttributeColumnsWithLocaleScoping(): void
    {
        $product = $this->newProduct('SKU-A');
        $descAttribute = $this->newAttribute('description', AttributeType::Text);

        $valuesByObject = [
            spl_object_id($product) => [
                new ObjectValue($product, $descAttribute, ['value' => 'Polski opis'], locale: 'pl'),
                new ObjectValue($product, $descAttribute, ['value' => 'English description'], locale: 'en'),
            ],
        ];

        $builder = $this->newBuilder(valuesByObject: $valuesByObject, categoriesByObject: []);
        $session = $this->newSessionWithTenant(['sku', 'description.pl', 'description.en']);
        $rows = iterator_to_array($builder->build([$product], $session));

        self::assertSame([
            'sku' => 'SKU-A',
            'description.pl' => 'Polski opis',
            'description.en' => 'English description',
        ], $rows[0]);
    }

    #[Test]
    public function rendersAttributeColumnsWithChannelScoping(): void
    {
        // #1229 — `description.shopify` resolves to the channel-scoped value;
        // the bare `description` column still emits the global value, and a
        // channel column whose channel does not resolve stays blank (it does
        // NOT silently fall back to the global value).
        $product = $this->newProduct('SKU-CH');
        $desc = $this->newAttribute('description', AttributeType::Text);
        $shopifyId = Uuid::v7();

        $valuesByObject = [
            spl_object_id($product) => [
                new ObjectValue($product, $desc, ['value' => 'Global desc']),
                new ObjectValue($product, $desc, ['value' => 'Shopify desc'], channelId: $shopifyId),
            ],
        ];

        $builder = $this->newBuilder(
            valuesByObject: $valuesByObject,
            categoriesByObject: [],
            channelIds: ['shopify' => $shopifyId],
        );
        $session = $this->newSessionWithTenant(
            ['sku', 'description', 'description.shopify', 'description.allegro'],
            channels: ['shopify', 'allegro'],
        );
        $rows = iterator_to_array($builder->build([$product], $session));

        self::assertSame([
            'sku' => 'SKU-CH',
            'description' => 'Global desc',
            'description.shopify' => 'Shopify desc',
            'description.allegro' => '',
        ], $rows[0]);
    }

    #[Test]
    public function missingAttributeYieldsBlankCell(): void
    {
        // R-47 from PRD §14 — profile references attribute that does not
        // exist on the row. Builder MUST NOT 500 — emit blank cell.
        $product = $this->newProduct('SKU-B');

        $builder = $this->newBuilder(
            valuesByObject: [spl_object_id($product) => []],
            categoriesByObject: [],
        );
        $session = $this->newSessionWithTenant(['sku', 'does_not_exist', 'description.pl']);
        $rows = iterator_to_array($builder->build([$product], $session));

        self::assertSame(['sku' => 'SKU-B', 'does_not_exist' => '', 'description.pl' => ''], $rows[0]);
    }

    #[Test]
    public function multiselectAttributeSerialisesWithPipe(): void
    {
        // PRD §8.2 — round-trip default. Pairs with IMP-17 (#603).
        $product = $this->newProduct('SKU-C');
        $tags = $this->newAttribute('tags', AttributeType::Multiselect);

        $builder = $this->newBuilder(
            valuesByObject: [
                spl_object_id($product) => [
                    new ObjectValue($product, $tags, ['option_codes' => ['promo', 'bestseller']]),
                ],
            ],
            categoriesByObject: [],
        );
        $session = $this->newSessionWithTenant(['sku', 'tags']);
        $rows = iterator_to_array($builder->build([$product], $session));

        self::assertSame(['sku' => 'SKU-C', 'tags' => 'promo|bestseller'], $rows[0]);
    }

    #[Test]
    public function categoryColumnConcatenatesCategoryCodes(): void
    {
        $product = $this->newProduct('SKU-D');
        $catA = $this->newProduct('CAT-A', null, ObjectKind::Category);
        $catB = $this->newProduct('CAT-B', null, ObjectKind::Category);

        $assignmentA = $this->createStub(ObjectCategory::class);
        $assignmentA->method('getCategory')->willReturn($catA);
        $assignmentB = $this->createStub(ObjectCategory::class);
        $assignmentB->method('getCategory')->willReturn($catB);

        $builder = $this->newBuilder(
            valuesByObject: [],
            categoriesByObject: [spl_object_id($product) => [$assignmentA, $assignmentB]],
        );
        $session = $this->newSessionWithTenant(['sku', 'category']);
        $rows = iterator_to_array($builder->build([$product], $session));

        self::assertSame(['sku' => 'SKU-D', 'category' => 'CAT-A|CAT-B'], $rows[0]);
    }

    // ----- helpers -----

    /**
     * @param array<int, list<ObjectValue>>    $valuesByObject     keyed by spl_object_id($product)
     * @param array<int, list<ObjectCategory>> $categoriesByObject same
     * @param array<string, Uuid>              $channelIds         channel code => id (#1229)
     */
    private function newBuilder(
        array $valuesByObject,
        array $categoriesByObject,
        array $channelIds = [],
    ): ExportBuilder {
        $values = $this->createStub(ObjectValueRepositoryInterface::class);
        $values->method('findByObject')->willReturnCallback(
            static fn (CatalogObject $object): array => $valuesByObject[spl_object_id($object)] ?? []
        );

        $categories = $this->createStub(ObjectCategoryRepositoryInterface::class);
        $categories->method('findByProduct')->willReturnCallback(
            static fn (CatalogObject $object): array => $categoriesByObject[spl_object_id($object)] ?? []
        );

        $channels = $this->createStub(ChannelResolverInterface::class);
        $channels->method('resolveId')->willReturnCallback(
            static fn (string $code): ?Uuid => $channelIds[$code] ?? null
        );

        return new ExportBuilder(
            values: $values,
            categories: $categories,
            columnResolver: new ColumnResolver(),
            serializer: new ValueSerializer(),
            channels: $channels,
        );
    }

    private function newProduct(string $code, ?CatalogObject $parent = null, ObjectKind $kind = ObjectKind::Product): CatalogObject
    {
        $objectType = $this->createStub(ObjectType::class);
        $objectType->method('getKind')->willReturn($kind);

        $object = new CatalogObject(
            objectType: $objectType,
            code: $code,
        );
        if (null !== $parent) {
            $object->assignParent($parent);
        }

        return $object;
    }

    private function newAttribute(string $code, AttributeType $type): Attribute
    {
        $attribute = $this->createStub(Attribute::class);
        $attribute->method('getCode')->willReturn($code);
        $attribute->method('getType')->willReturn($type);

        return $attribute;
    }

    /**
     * @param list<string>      $selectedColumns
     * @param list<string>|null $channels
     */
    private function newSession(array $selectedColumns, ?array $channels = null): ExportSession
    {
        return new ExportSession(
            userId: Uuid::v7(),
            source: ExportSource::ListContext,
            format: ExportFormat::Xlsx,
            targetScope: ExportTargetScope::Selected,
            selectedColumns: $selectedColumns,
            channels: $channels,
        );
    }

    /**
     * @param list<string>      $selectedColumns
     * @param list<string>|null $channels
     */
    private function newSessionWithTenant(array $selectedColumns, ?array $channels = null): ExportSession
    {
        $session = $this->newSession($selectedColumns, $channels);
        $session->assignTenant(new Tenant('demo', 'Demo'));

        return $session;
    }
}
