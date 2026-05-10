<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectCategoryRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Integration coverage for the `object_categories` junction repository
 * (PCAT-01 / #474). Verifies atomic replace, primary lookup, and the
 * partial unique index that enforces at-most-one primary per product.
 */
final class DoctrineObjectCategoryRepositoryTest extends KernelTestCase
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

        self::getContainer()->get(BuiltInObjectTypeSeeder::class)->seed($this->tenant);
    }

    #[Test]
    public function freshProductHasNoAssignmentsAndNoPrimary(): void
    {
        $product = $this->makeProduct('SKU-empty');

        self::assertSame([], $this->repo()->findByProduct($product));
        self::assertNull($this->repo()->findPrimary($product));
    }

    #[Test]
    public function replaceForProductPersistsAssignmentsAndPrimaryAtomically(): void
    {
        $product = $this->makeProduct('SKU-1');
        $cat1 = $this->makeCategory('c1');
        $cat2 = $this->makeCategory('c2');

        $this->repo()->replaceForProduct(
            $product,
            [$cat1->getId(), $cat2->getId()],
            $cat2->getId(),
        );

        $rows = $this->repo()->findByProduct($product);
        self::assertCount(2, $rows);
        self::assertSame($cat1->getId()->toRfc4122(), $rows[0]->getCategory()->getId()->toRfc4122());
        self::assertSame(0, $rows[0]->getPosition());
        self::assertFalse($rows[0]->isPrimary());
        self::assertSame($cat2->getId()->toRfc4122(), $rows[1]->getCategory()->getId()->toRfc4122());
        self::assertSame(1, $rows[1]->getPosition());
        self::assertTrue($rows[1]->isPrimary());

        $primary = $this->repo()->findPrimary($product);
        self::assertNotNull($primary);
        self::assertSame($cat2->getId()->toRfc4122(), $primary->getCategory()->getId()->toRfc4122());
    }

    #[Test]
    public function replaceForProductWipesOldRowsBeforeReinsert(): void
    {
        $product = $this->makeProduct('SKU-2');
        $cat1 = $this->makeCategory('c1');
        $cat2 = $this->makeCategory('c2');

        // First state: cat1 only, primary
        $this->repo()->replaceForProduct($product, [$cat1->getId()], $cat1->getId());
        self::assertCount(1, $this->repo()->findByProduct($product));

        // Replace with cat2 only — cat1 row must be gone, no primary index conflict
        $this->repo()->replaceForProduct($product, [$cat2->getId()], $cat2->getId());

        $rows = $this->repo()->findByProduct($product);
        self::assertCount(1, $rows);
        self::assertSame($cat2->getId()->toRfc4122(), $rows[0]->getCategory()->getId()->toRfc4122());
        self::assertTrue($rows[0]->isPrimary());
    }

    #[Test]
    public function replaceForProductWithEmptyListClearsAllAssignments(): void
    {
        $product = $this->makeProduct('SKU-3');
        $cat = $this->makeCategory('c1');
        $this->repo()->replaceForProduct($product, [$cat->getId()], $cat->getId());

        $this->repo()->replaceForProduct($product, [], null);

        self::assertSame([], $this->repo()->findByProduct($product));
        self::assertNull($this->repo()->findPrimary($product));
    }

    #[Test]
    public function replaceForProductRejectsPrimaryNotInList(): void
    {
        $product = $this->makeProduct('SKU-4');
        $cat1 = $this->makeCategory('c1');
        $cat2 = $this->makeCategory('c2');

        $this->expectException(InvalidArgumentException::class);
        $this->repo()->replaceForProduct($product, [$cat1->getId()], $cat2->getId());
    }

    #[Test]
    public function replaceForProductRejectsPrimaryWithEmptyCategoryList(): void
    {
        $product = $this->makeProduct('SKU-5');
        $cat = $this->makeCategory('c1');

        $this->expectException(InvalidArgumentException::class);
        $this->repo()->replaceForProduct($product, [], $cat->getId());
    }

    #[Test]
    public function replaceForProductReorderingPrimarySwapsPrimaryFlagAtomically(): void
    {
        // Application-level guarantee that the partial unique index in the
        // production schema is also enforced at the API boundary: even when
        // the same product is replaced multiple times with a different
        // primary, exactly one row carries `is_primary=true` after each call.
        // (The DB-level partial unique itself is exercised by the manual
        // smoke described in PCAT-01 — Foundry's ResetDatabase rebuilds the
        // schema from ORM mapping which cannot express partial indexes, so
        // the constraint is not present in the test database.)
        $product = $this->makeProduct('SKU-6');
        $cat1 = $this->makeCategory('c1');
        $cat2 = $this->makeCategory('c2');

        $this->repo()->replaceForProduct($product, [$cat1->getId(), $cat2->getId()], $cat1->getId());
        $primaryFirst = $this->repo()->findPrimary($product);
        self::assertNotNull($primaryFirst);
        self::assertSame($cat1->getId()->toRfc4122(), $primaryFirst->getCategory()->getId()->toRfc4122());

        $this->repo()->replaceForProduct($product, [$cat1->getId(), $cat2->getId()], $cat2->getId());
        $primarySecond = $this->repo()->findPrimary($product);
        self::assertNotNull($primarySecond);
        self::assertSame($cat2->getId()->toRfc4122(), $primarySecond->getCategory()->getId()->toRfc4122());

        $primaryRows = array_filter(
            $this->repo()->findByProduct($product),
            static fn (ObjectCategory $a) => $a->isPrimary(),
        );
        self::assertCount(1, $primaryRows);
    }

    #[Test]
    public function findOneReturnsExistingTupleAndNullForMissing(): void
    {
        $product = $this->makeProduct('SKU-7');
        $cat1 = $this->makeCategory('c1');
        $cat2 = $this->makeCategory('c2');
        $this->repo()->replaceForProduct($product, [$cat1->getId()], $cat1->getId());

        self::assertNotNull($this->repo()->findOne($product, $cat1));
        self::assertNull($this->repo()->findOne($product, $cat2));
    }

    #[Test]
    public function findByCategoryReturnsAssignmentsForReverseListing(): void
    {
        $cat = $this->makeCategory('cshared');
        $p1 = $this->makeProduct('SKU-r1');
        $p2 = $this->makeProduct('SKU-r2');
        $this->repo()->replaceForProduct($p1, [$cat->getId()], $cat->getId());
        $this->repo()->replaceForProduct($p2, [$cat->getId()], null);

        $rows = $this->repo()->findByCategory($cat);

        self::assertCount(2, $rows);
        $codes = array_map(static fn (ObjectCategory $a) => $a->getProduct()->getCode(), $rows);
        sort($codes);
        self::assertSame(['SKU-r1', 'SKU-r2'], $codes);
    }

    private function repo(): ObjectCategoryRepositoryInterface
    {
        return self::getContainer()->get(ObjectCategoryRepositoryInterface::class);
    }

    private function makeProduct(string $code): CatalogObject
    {
        $em = $this->em();
        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)->findBuiltInByKind(ObjectKind::Product, $this->tenant);
        \assert(null !== $type);
        $product = new CatalogObject($type, $code);
        $em->persist($product);
        $em->flush();

        return $product;
    }

    private function makeCategory(string $code): CatalogObject
    {
        $em = $this->em();
        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)->findBuiltInByKind(ObjectKind::Category, $this->tenant);
        \assert(null !== $type);
        $category = new CatalogObject($type, $code);
        $category->attachToPath($code);
        $em->persist($category);
        $em->flush();

        return $category;
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
