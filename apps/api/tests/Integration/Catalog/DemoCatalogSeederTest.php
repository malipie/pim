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
        // 28 attributes (19 base + 8 VIEW-02 mockup additions: ip_rating,
        // vat_rate, currency_code, warranty_months, voltage, power_w,
        // requires_referral, eol_date + 1 VIEW-07.2 `description_rich`
        // wysiwyg) spanning the 11 user-facing AttributeType cases.
        self::assertSame(28, (int) $em->createQuery('SELECT COUNT(a) FROM '.Attribute::class.' a')->getSingleScalarResult());
        // 100 products + 5 categories + 10 assets.
        self::assertSame(100, $this->countObjects(ObjectKind::Product));
        self::assertSame(5, $this->countObjects(ObjectKind::Category));
        self::assertSame(10, $this->countObjects(ObjectKind::Asset));
        // 16 product junctions (incl. VIEW-07.2 `description_rich`) +
        // 4 category junctions + 3 asset junctions.
        // Composite PK on ObjectTypeAttribute prevents DQL COUNT — drop to DBAL.
        $junctions = $em->getConnection()->fetchOne('SELECT COUNT(*) FROM object_type_attributes');
        self::assertSame(23, (int) (\is_scalar($junctions) ? $junctions : 0));
        // 100 × 15 + 5 × 3 + 10 × 2 = 1535 ObjectValue rows.
        self::assertSame(1535, (int) $em->createQuery('SELECT COUNT(v) FROM '.ObjectValue::class.' v')->getSingleScalarResult());
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
    public function categorySchemaIncludesOwnUserDefinedAttributes(): void
    {
        // Proof of ADR-009: categories carry their own user-defined fields,
        // not just inherited product attributes.
        $seeder = self::getContainer()->get(DemoCatalogSeeder::class);
        $seeder->seed($this->tenant);

        $em = $this->em();
        $em->clear();

        $category = self::getContainer()->get(\App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface::class)
            ->findByCode('CAT-FOOTWEAR', ObjectKind::Category, $this->tenant);
        self::assertNotNull($category);
        $indexed = $category->getAttributesIndexed();

        self::assertArrayHasKey('name', $indexed);
        self::assertArrayHasKey('seo_title', $indexed);
        self::assertArrayHasKey('seo_description', $indexed);
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
