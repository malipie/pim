<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectCategoryRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * PCAT-03 (#476) — when a category is removed, its primary assignment
 * cascades away. The listener must promote the next-oldest remaining
 * assignment to primary so the product never stays in a "had assignments
 * but no primary" state.
 */
final class PrimaryCategoryRepairListenerTest extends KernelTestCase
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
    public function removingPrimaryCategoryPromotesOldestRemainingAssignment(): void
    {
        $product = $this->makeProduct('sku-promote');
        $primary = $this->makeCategory('primary-cat');
        $alt1 = $this->makeCategory('alt-1');
        $alt2 = $this->makeCategory('alt-2');

        // Order matters for the promote-next tie-breaker (position ASC,
        // created_at ASC). replaceForProduct assigns positions in the
        // order of the categoryIds list, so alt-1 is the oldest non-primary.
        $this->repo()->replaceForProduct(
            $product,
            [$primary->getId(), $alt1->getId(), $alt2->getId()],
            $primary->getId(),
        );

        $this->em()->remove($primary);
        $this->em()->flush();
        // Listener uses raw DBAL UPDATE in postFlush (managed entities are
        // already detached after cascade). Clear the Identity Map so
        // subsequent reads hydrate from the freshly-mutated DB rows
        // instead of the stale cached entities.
        $this->em()->clear();

        $newPrimary = $this->repo()->findPrimary($product);
        self::assertNotNull($newPrimary);
        self::assertSame('alt-1', $newPrimary->getCategory()->getCode());

        // Sanity: still exactly one primary across all remaining rows.
        $primaryCount = 0;
        foreach ($this->repo()->findByProduct($product) as $assignment) {
            if ($assignment->isPrimary()) {
                ++$primaryCount;
            }
        }
        self::assertSame(1, $primaryCount);
    }

    #[Test]
    public function removingOnlyPrimaryLeavesProductWithoutPrimary(): void
    {
        $product = $this->makeProduct('sku-only');
        $only = $this->makeCategory('only-cat');

        $this->repo()->replaceForProduct($product, [$only->getId()], $only->getId());

        $this->em()->remove($only);
        $this->em()->flush();
        $this->em()->clear();

        self::assertNull($this->repo()->findPrimary($product));
        self::assertSame([], $this->repo()->findByProduct($product));
    }

    #[Test]
    public function removingNonPrimaryCategoryDoesNotTouchExistingPrimary(): void
    {
        $product = $this->makeProduct('sku-untouched');
        $primary = $this->makeCategory('keep-primary');
        $extra = $this->makeCategory('to-remove');

        $this->repo()->replaceForProduct(
            $product,
            [$primary->getId(), $extra->getId()],
            $primary->getId(),
        );

        $this->em()->remove($extra);
        $this->em()->flush();
        $this->em()->clear();

        $current = $this->repo()->findPrimary($product);
        self::assertNotNull($current);
        self::assertSame('keep-primary', $current->getCategory()->getCode());
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
        $category->attachToPath(str_replace('-', '_', $code));
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
