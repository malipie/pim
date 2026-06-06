<?php

declare(strict_types=1);

namespace App\Tests\Integration\Catalog;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Application\Handler\CheckSchemaDriftHandler;
use App\Catalog\Application\Message\CheckSchemaDriftForCategory;
use App\Catalog\Application\Subscriber\SchemaSnapshotListener;
use App\Catalog\Contracts\Event\ObjectAttributesChanged;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * CHC-04 (#1288) — snapshot capture + async drift detection.
 */
final class SchemaDriftTest extends KernelTestCase
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
    public function listenerCapturesSnapshotOnFirstFill(): void
    {
        $product = $this->makeProduct('SKU-1');

        $this->listener()(new ObjectAttributesChanged($product->getId(), $this->tenant->getId()));

        $this->em()->clear();
        $reloaded = $this->em()->find(CatalogObject::class, $product->getId());
        \assert($reloaded instanceof CatalogObject);
        $snapshot = $reloaded->getSchemaSnapshot();
        self::assertNotNull($snapshot);
        self::assertArrayHasKey('attributeGroupIds', $snapshot);
    }

    #[Test]
    public function listenerDoesNotOverwriteExistingSnapshot(): void
    {
        $product = $this->makeProduct('SKU-1');
        $product->recordSchemaSnapshot(['attributeGroupIds' => ['frozen'], 'capturedAt' => 'x', 'masterCategoryId' => null]);
        $this->em()->flush();

        $this->listener()(new ObjectAttributesChanged($product->getId(), $this->tenant->getId()));

        $this->em()->clear();
        $reloaded = $this->em()->find(CatalogObject::class, $product->getId());
        \assert($reloaded instanceof CatalogObject);
        self::assertSame(['frozen'], $reloaded->getSchemaSnapshot()['attributeGroupIds'] ?? null);
    }

    #[Test]
    public function handlerFlagsDriftWhenSnapshotDiffersFromCurrent(): void
    {
        $category = $this->makeCategory('driftcat');
        $product = $this->makeProduct('SKU-1');
        $this->assign($product, $category);

        // Snapshot references a group id that the product's current effective
        // set does not contain → drift.
        $product->recordSchemaSnapshot([
            'attributeGroupIds' => [Uuid::v7()->toRfc4122()],
            'capturedAt' => 'x',
            'masterCategoryId' => null,
        ]);
        $this->em()->flush();

        $this->handler()(new CheckSchemaDriftForCategory(
            $category->getId()->toRfc4122(),
            $this->tenant->getId()->toRfc4122(),
        ));

        $this->em()->clear();
        $reloaded = $this->em()->find(CatalogObject::class, $product->getId());
        \assert($reloaded instanceof CatalogObject);
        self::assertTrue($reloaded->getSchemaDrift());
    }

    #[Test]
    public function handlerLeavesNoDriftWhenSnapshotMatches(): void
    {
        $category = $this->makeCategory('matchcat');
        $product = $this->makeProduct('SKU-1');
        $this->assign($product, $category);

        // Snapshot == current effective set → no drift.
        $current = array_map(
            static fn (\App\Catalog\Domain\Entity\AttributeGroup $g): string => $g->getId()->toRfc4122(),
            self::getContainer()->get(\App\Catalog\Domain\Service\EffectiveAttributeGroupResolver::class)->resolve($product),
        );
        $product->recordSchemaSnapshot(['attributeGroupIds' => $current, 'capturedAt' => 'x', 'masterCategoryId' => null]);
        $this->em()->flush();

        $this->handler()(new CheckSchemaDriftForCategory(
            $category->getId()->toRfc4122(),
            $this->tenant->getId()->toRfc4122(),
        ));

        $this->em()->clear();
        $reloaded = $this->em()->find(CatalogObject::class, $product->getId());
        \assert($reloaded instanceof CatalogObject);
        self::assertFalse($reloaded->getSchemaDrift());
    }

    private function makeProduct(string $sku): CatalogObject
    {
        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $this->tenant);
        \assert(null !== $type);
        $product = new CatalogObject($type, $sku);
        $this->em()->persist($product);
        $this->em()->flush();

        return $product;
    }

    private function makeCategory(string $code): CatalogObject
    {
        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Category, $this->tenant);
        \assert(null !== $type);
        $category = new CatalogObject($type, $code);
        $this->em()->persist($category);
        $this->em()->flush();

        return $category;
    }

    private function assign(CatalogObject $product, CatalogObject $category): void
    {
        $this->em()->persist(new ObjectCategory($product, $category, true));
        $this->em()->flush();
    }

    private function listener(): SchemaSnapshotListener
    {
        return self::getContainer()->get(SchemaSnapshotListener::class);
    }

    private function handler(): CheckSchemaDriftHandler
    {
        return self::getContainer()->get(CheckSchemaDriftHandler::class);
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
