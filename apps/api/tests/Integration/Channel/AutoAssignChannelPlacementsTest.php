<?php

declare(strict_types=1);

namespace App\Tests\Integration\Channel;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Contracts\Event\ObjectPrimaryCategoryAssigned;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Channel\Application\Subscriber\AssignChannelPlacementsOnPrimaryCategoryAssigned;
use App\Channel\Domain\ChannelPlacementSource;
use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelCategoryNode;
use App\Channel\Domain\Repository\ChannelCategoryNodeMappingRepositoryInterface;
use App\Channel\Domain\Repository\ObjectChannelPlacementRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * CHC-07 (#1290) — placement auto-assignment from node mappings, end-to-end
 * over the real repositories + Postgres (the handler is invoked directly so
 * the test does not depend on the async transport — same shape as
 * {@see \App\Tests\Integration\Catalog\SchemaDriftTest}).
 */
final class AutoAssignChannelPlacementsTest extends KernelTestCase
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
    public function createsAutoPlacementOnChannelThatMappedTheMasterCategory(): void
    {
        $product = $this->makeProduct('SKU-1');
        $category = $this->makeCategory('cat-tv');
        $channel = $this->makeChannel('allegro');
        $node = $this->makeNode($channel, 'rtv');
        $this->mapMaster($channel, $category, [$node]);

        $this->handler()(new ObjectPrimaryCategoryAssigned($product->getId(), $this->tenant->getId(), $category->getId()));

        $placement = $this->placements()->findByObjectAndChannel($product->getId(), $channel->getId());
        self::assertNotNull($placement);
        self::assertTrue($placement->getNodeId()->equals($node->getId()));
        self::assertSame(ChannelPlacementSource::Auto, $placement->getSource());
    }

    #[Test]
    public function fansOutAcrossEveryChannelWithAMapping(): void
    {
        $product = $this->makeProduct('SKU-1');
        $category = $this->makeCategory('cat-tv');

        $allegro = $this->makeChannel('allegro');
        $shopify = $this->makeChannel('shopify');
        $this->mapMaster($allegro, $category, [$this->makeNode($allegro, 'rtv')]);
        $this->mapMaster($shopify, $category, [$this->makeNode($shopify, 'electronics')]);

        $this->handler()(new ObjectPrimaryCategoryAssigned($product->getId(), $this->tenant->getId(), $category->getId()));

        self::assertCount(2, $this->placements()->findByObject($product->getId()));
    }

    #[Test]
    public function neverOverwritesAManualPlacement(): void
    {
        $product = $this->makeProduct('SKU-1');
        $category = $this->makeCategory('cat-tv');
        $channel = $this->makeChannel('allegro');
        $mapped = $this->makeNode($channel, 'rtv');
        $manualNode = $this->makeNode($channel, 'agd');
        $this->mapMaster($channel, $category, [$mapped]);

        // Operator placed the product by hand on a different node.
        $this->placements()->upsert($product->getId(), $channel, $manualNode, ChannelPlacementSource::Manual);

        $this->handler()(new ObjectPrimaryCategoryAssigned($product->getId(), $this->tenant->getId(), $category->getId()));

        $placement = $this->placements()->findByObjectAndChannel($product->getId(), $channel->getId());
        self::assertNotNull($placement);
        self::assertSame(ChannelPlacementSource::Manual, $placement->getSource());
        self::assertTrue($placement->getNodeId()->equals($manualNode->getId()), 'manual node must be preserved');
    }

    #[Test]
    public function repointsAnExistingAutoPlacement(): void
    {
        $product = $this->makeProduct('SKU-1');
        $category = $this->makeCategory('cat-tv');
        $channel = $this->makeChannel('allegro');
        $oldNode = $this->makeNode($channel, 'old');
        $newNode = $this->makeNode($channel, 'new');
        $this->mapMaster($channel, $category, [$newNode]);

        $this->placements()->upsert($product->getId(), $channel, $oldNode, ChannelPlacementSource::Auto);

        $this->handler()(new ObjectPrimaryCategoryAssigned($product->getId(), $this->tenant->getId(), $category->getId()));

        $placement = $this->placements()->findByObjectAndChannel($product->getId(), $channel->getId());
        self::assertNotNull($placement);
        self::assertTrue($placement->getNodeId()->equals($newNode->getId()));
        self::assertSame(ChannelPlacementSource::Auto, $placement->getSource());
    }

    #[Test]
    public function doesNothingWhenCategoryHasNoMappings(): void
    {
        $product = $this->makeProduct('SKU-1');
        $category = $this->makeCategory('cat-orphan');
        $this->makeChannel('allegro');

        $this->handler()(new ObjectPrimaryCategoryAssigned($product->getId(), $this->tenant->getId(), $category->getId()));

        self::assertCount(0, $this->placements()->findByObject($product->getId()));
    }

    /**
     * @param list<ChannelCategoryNode> $nodes
     */
    private function mapMaster(Channel $channel, CatalogObject $category, array $nodes): void
    {
        $this->mappings()->upsert(
            $channel,
            $category->getId(),
            array_map(static fn (ChannelCategoryNode $n): string => $n->getId()->toRfc4122(), $nodes),
        );
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

    private function makeChannel(string $code): Channel
    {
        $channel = new Channel($code, ['pl' => $code]);
        $this->em()->persist($channel);
        $this->em()->flush();

        return $channel;
    }

    private function makeNode(Channel $channel, string $code): ChannelCategoryNode
    {
        $node = new ChannelCategoryNode($channel, $code, ['pl' => $code]);
        $node->attachToPath($node->ltreeLabel());
        $this->em()->persist($node);
        $this->em()->flush();

        return $node;
    }

    private function handler(): AssignChannelPlacementsOnPrimaryCategoryAssigned
    {
        return self::getContainer()->get(AssignChannelPlacementsOnPrimaryCategoryAssigned::class);
    }

    private function mappings(): ChannelCategoryNodeMappingRepositoryInterface
    {
        return self::getContainer()->get(ChannelCategoryNodeMappingRepositoryInterface::class);
    }

    private function placements(): ObjectChannelPlacementRepositoryInterface
    {
        return self::getContainer()->get(ObjectChannelPlacementRepositoryInterface::class);
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
