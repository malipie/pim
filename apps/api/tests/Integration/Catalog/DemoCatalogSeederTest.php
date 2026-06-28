<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Application\DemoCatalogSeeder;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\ObjectKind;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class DemoCatalogSeederTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $em = $this->em();
        $this->tenant = new Tenant('demo', 'Demo');
        $em->persist($this->tenant);
        $em->flush();
        $this->tenantContext()->set($this->tenant);

        // Built-in ObjectTypes are a precondition. The DemoCatalogSeeder
        // assumes the platform already onboarded the tenant.
        self::getContainer()->get(BuiltInObjectTypeSeeder::class)->seed($this->tenant);
    }

    #[Test]
    public function seedsExpectedCountsAcrossDomainTables(): void
    {
        $seeder = self::getContainer()->get(DemoCatalogSeeder::class);

        $seeder->seed($this->tenant);

        $em = $this->em();
        // 24 attributes: 16 product/base + 8 VIEW-02 mockup additions
        // (ip_rating, vat_rate, currency_code, warranty_months, voltage,
        // power_w, requires_referral, eol_date). seo_title / seo_description /
        // alt_text / caption were dropped when Asset / Category became closed
        // system kinds (amends ADR-009).
        self::assertSame(24, (int) $em->createQuery('SELECT COUNT(a) FROM '.Attribute::class.' a')->getSingleScalarResult());
        // 100 products + 5 categories + 10 assets.
        self::assertSame(100, $this->countObjects(ObjectKind::Product));
        self::assertSame(5, $this->countObjects(ObjectKind::Category));
        self::assertSame(10, $this->countObjects(ObjectKind::Asset));
        // 16 product junctions only — Asset / Category are closed system kinds
        // and carry zero `object_type_attributes` rows.
        // Composite PK on ObjectTypeAttribute prevents DQL COUNT — drop to DBAL.
        $junctions = $em->getConnection()->fetchOne('SELECT COUNT(*) FROM object_type_attributes');
        self::assertSame(16, (int) (\is_scalar($junctions) ? $junctions : 0));
        // 100 × 15 (product) + 5 × 1 (category name) + 10 × 1 (asset name)
        // = 1515 global ObjectValue rows + #1259: first 5 products each get
        // per-locale EN name + description + short_description (5 × 3 = 15).
        // No channelId here → no per-channel rows.
        self::assertSame(1530, (int) $em->createQuery('SELECT COUNT(v) FROM '.ObjectValue::class.' v')->getSingleScalarResult());
    }

    #[Test]
    public function attributesIndexedCacheMatchesObjectValuePayloadForFirstSku(): void
    {
        $seeder = self::getContainer()->get(DemoCatalogSeeder::class);
        $seeder->seed($this->tenant);

        $em = $this->em();
        $em->clear();

        $product = self::getContainer()->get(\App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface::class)
            ->findByCode('DEMO-001', ObjectKind::Product, $this->tenant);
        self::assertNotNull($product);
        $indexed = $product->getAttributesIndexed();

        // Spot-check the JSONB cache shapes match #34's polymorphic schema:
        // each AttributeType variant has a distinct payload key.
        self::assertSame('DEMO-001', self::asArray($indexed['sku'])['value']);
        self::assertArrayHasKey('option_code', self::asArray($indexed['color']));
        self::assertArrayHasKey('option_codes', self::asArray($indexed['tags']));
        self::assertArrayHasKey('amount', self::asArray($indexed['price']));
        self::assertArrayHasKey('currency', self::asArray($indexed['price']));
        self::assertArrayHasKey('asset_id', self::asArray($indexed['main_image']));
        self::assertArrayHasKey('object_id', self::asArray($indexed['related_to']));
    }

    /**
     * @return array<mixed>
     */
    private static function asArray(mixed $value): array
    {
        self::assertIsArray($value);

        return $value;
    }

    #[Test]
    public function marksSensibleProductAttributesFilterable(): void
    {
        // #1354 — the advanced filter panel offers ONLY `is_filterable`
        // attributes. The demo must therefore seed a usable filterable
        // subset (otherwise the panel renders an empty picker on demo
        // data) while leaving long-form / system fields non-filterable.
        $seeder = self::getContainer()->get(DemoCatalogSeeder::class);
        $seeder->seed($this->tenant);

        $em = $this->em();
        $em->clear();

        $repo = self::getContainer()->get(\App\Catalog\Domain\Repository\AttributeRepositoryInterface::class);

        foreach (['brand', 'color', 'size', 'tags', 'price', 'weight', 'height', 'in_stock', 'release_date'] as $code) {
            $attribute = $repo->findByCode($code, $this->tenant);
            self::assertNotNull($attribute, \sprintf('Attribute "%s" should be seeded.', $code));
            self::assertTrue($attribute->isFilterable(), \sprintf('Attribute "%s" must be filterable.', $code));
        }

        // Long-form / identifier fields stay out of the filter picker.
        foreach (['description', 'short_description', 'description_html', 'sku'] as $code) {
            $attribute = $repo->findByCode($code, $this->tenant);
            self::assertNotNull($attribute);
            self::assertFalse($attribute->isFilterable(), \sprintf('Attribute "%s" must NOT be filterable.', $code));
        }
    }

    #[Test]
    public function assetAndCategoryAreClosedSystemKindsWithNoAttachedAttributes(): void
    {
        // Amends ADR-009: Asset / Category are closed system kinds. They
        // carry zero `object_type_attributes` junctions and only the
        // intrinsic `name` (display label) in their indexed cache — no
        // seo_title / seo_description / alt_text / caption.
        $seeder = self::getContainer()->get(DemoCatalogSeeder::class);
        $seeder->seed($this->tenant);

        $em = $this->em();
        $em->clear();

        $repo = self::getContainer()->get(\App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface::class);
        $objectRepo = self::getContainer()->get(\App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface::class);

        foreach ([ObjectKind::Category, ObjectKind::Asset] as $kind) {
            $type = $repo->findBuiltInByKind($kind, $this->tenant);
            self::assertNotNull($type);
            $count = $em->getConnection()->fetchOne(
                'SELECT COUNT(*) FROM object_type_attributes WHERE object_type_id = ?',
                [$type->getId()->toRfc4122()],
            );
            self::assertSame(0, (int) (\is_scalar($count) ? $count : -1), \sprintf('%s must have zero attached attributes.', $kind->value));
        }

        $category = $objectRepo->findByCode('CAT-FOOTWEAR', ObjectKind::Category, $this->tenant);
        self::assertNotNull($category);
        $indexed = $category->getAttributesIndexed();
        self::assertArrayHasKey('name', $indexed);
        self::assertArrayNotHasKey('seo_title', $indexed);
        self::assertArrayNotHasKey('seo_description', $indexed);

        $asset = $objectRepo->findByCode('ASSET-001', ObjectKind::Asset, $this->tenant);
        self::assertNotNull($asset);
        $assetIndexed = $asset->getAttributesIndexed();
        self::assertArrayHasKey('name', $assetIndexed);
        self::assertArrayNotHasKey('alt_text', $assetIndexed);
    }

    #[Test]
    public function secondInvocationIsAnIdempotentNoOp(): void
    {
        $seeder = self::getContainer()->get(DemoCatalogSeeder::class);
        $seeder->seed($this->tenant);

        $beforeProducts = $this->countObjects(ObjectKind::Product);
        $beforeAttributes = (int) $this->em()->createQuery('SELECT COUNT(a) FROM '.Attribute::class.' a')->getSingleScalarResult();

        $seeder->seed($this->tenant);

        self::assertSame($beforeProducts, $this->countObjects(ObjectKind::Product));
        self::assertSame($beforeAttributes, (int) $this->em()->createQuery('SELECT COUNT(a) FROM '.Attribute::class.' a')->getSingleScalarResult());
    }

    #[Test]
    public function seedsPerLocaleAndPerChannelDemoValues(): void
    {
        $channelId = \Symfony\Component\Uid\Uuid::v7();
        $seeder = self::getContainer()->get(DemoCatalogSeeder::class);
        $seeder->seed($this->tenant, $channelId);

        $em = $this->em();

        // First 5 products each get name + description + short_description (EN) → 15 rows.
        $perLocale = (int) $em->createQuery(
            'SELECT COUNT(v) FROM '.ObjectValue::class." v WHERE v.locale = 'en'",
        )->getSingleScalarResult();
        self::assertSame(15, $perLocale, 'First 5 products carry EN name + description + short_description.');

        // First 5 products each get a per-channel price + short_description override → 10 rows.
        $perChannel = (int) $em->createQuery(
            'SELECT COUNT(v) FROM '.ObjectValue::class.' v WHERE v.channelId = :ch',
        )->setParameter('ch', $channelId->toRfc4122())->getSingleScalarResult();
        self::assertSame(10, $perChannel, 'First 5 products carry an Allegro price + short_description override.');
    }

    #[Test]
    public function builtInObjectTypeWiringPointsAtDemoAttributes(): void
    {
        $seeder = self::getContainer()->get(DemoCatalogSeeder::class);
        $seeder->seed($this->tenant);

        $em = $this->em();
        $em->clear();

        $repo = self::getContainer()->get(\App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface::class);
        $product = $repo->findBuiltInByKind(ObjectKind::Product, $this->tenant);
        self::assertNotNull($product);

        $label = $product->getLabelAttribute();
        self::assertNotNull($label);
        self::assertSame('name', $label->getCode());

        $image = $product->getImageAttribute();
        self::assertNotNull($image);
        self::assertSame('main_image', $image->getCode());

        self::assertSame(['required' => ['sku', 'name', 'description', 'price']], $product->getCompletenessRules());
    }

    private function countObjects(ObjectKind $kind): int
    {
        return (int) $this->em()
            ->createQuery('SELECT COUNT(o) FROM '.CatalogObject::class.' o WHERE o.kind = :kind')
            ->setParameter('kind', $kind->value)
            ->getSingleScalarResult();
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function tenantContext(): TenantContext
    {
        return self::getContainer()->get(TenantContext::class);
    }
}
