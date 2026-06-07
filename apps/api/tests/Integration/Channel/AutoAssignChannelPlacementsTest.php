<?php

declare(strict_types=1);

namespace App\Tests\Integration\Channel;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectCategory;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Channel\Application\Message\ReconcileChannelPlacementsForCategory;
use App\Channel\Application\Service\ReconcileObjectChannelPlacements;
use App\Channel\Application\Subscriber\ReconcileChannelPlacementsForCategoryHandler;
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
 * #1314 — placement reconcile from ALL of a product's categories (primary
 * precedence), manual-wins, stale-auto cleanup, and the mapping back-fill.
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
    public function placesProductViaPrimaryCategory(): void
    {
        $product = $this->makeProduct('SKU-1');
        $cat = $this->makeCategory('cat-tv');
        $this->assign($product, $cat, true, 0);
        $channel = $this->makeChannel('allegro');
        $node = $this->makeNode($channel, 'rtv');
        $this->mapMaster($channel, $cat, [$node]);

        $this->reconciler()->reconcile($product->getId(), $this->tenant->getId());

        $placement = $this->placements()->findByObjectAndChannel($product->getId(), $channel->getId());
        self::assertNotNull($placement);
        self::assertTrue($placement->getNodeId()->equals($node->getId()));
        self::assertSame(ChannelPlacementSource::Auto, $placement->getSource());
    }

    #[Test]
    public function placesProductViaNonPrimaryCategoryWhenPrimaryUnmapped(): void
    {
        // The operator's scenario: primary category is NOT mapped, a secondary is.
        $product = $this->makeProduct('SKU-1');
        $primary = $this->makeCategory('cat-apparel');
        $secondary = $this->makeCategory('cat-running');
        $this->assign($product, $primary, true, 0);
        $this->assign($product, $secondary, false, 1);
        $channel = $this->makeChannel('allegro');
        $node = $this->makeNode($channel, 'bieganie');
        $this->mapMaster($channel, $secondary, [$node]); // only the secondary is mapped

        $this->reconciler()->reconcile($product->getId(), $this->tenant->getId());

        $placement = $this->placements()->findByObjectAndChannel($product->getId(), $channel->getId());
        self::assertNotNull($placement, 'product lands via its non-primary mapped category');
        self::assertTrue($placement->getNodeId()->equals($node->getId()));
    }

    #[Test]
    public function primaryCategoryWinsOnConflict(): void
    {
        $product = $this->makeProduct('SKU-1');
        $primary = $this->makeCategory('cat-primary');
        $secondary = $this->makeCategory('cat-secondary');
        $this->assign($product, $primary, true, 0);
        $this->assign($product, $secondary, false, 1);
        $channel = $this->makeChannel('allegro');
        $primaryNode = $this->makeNode($channel, 'node-primary');
        $secondaryNode = $this->makeNode($channel, 'node-secondary');
        $this->mapMaster($channel, $primary, [$primaryNode]);
        $this->mapMaster($channel, $secondary, [$secondaryNode]);

        $this->reconciler()->reconcile($product->getId(), $this->tenant->getId());

        $placement = $this->placements()->findByObjectAndChannel($product->getId(), $channel->getId());
        self::assertNotNull($placement);
        self::assertTrue($placement->getNodeId()->equals($primaryNode->getId()), 'primary category wins');
    }

    #[Test]
    public function neverOverwritesManualPlacement(): void
    {
        $product = $this->makeProduct('SKU-1');
        $cat = $this->makeCategory('cat-tv');
        $this->assign($product, $cat, true, 0);
        $channel = $this->makeChannel('allegro');
        $mappedNode = $this->makeNode($channel, 'rtv');
        $manualNode = $this->makeNode($channel, 'agd');
        $this->mapMaster($channel, $cat, [$mappedNode]);
        $this->placements()->upsert($product->getId(), $channel, $manualNode, ChannelPlacementSource::Manual);

        $this->reconciler()->reconcile($product->getId(), $this->tenant->getId());

        $placement = $this->placements()->findByObjectAndChannel($product->getId(), $channel->getId());
        self::assertNotNull($placement);
        self::assertSame(ChannelPlacementSource::Manual, $placement->getSource());
        self::assertTrue($placement->getNodeId()->equals($manualNode->getId()), 'manual node preserved');
    }

    #[Test]
    public function removesStaleAutoPlacementWhenNoCategoryMapsTheChannel(): void
    {
        $product = $this->makeProduct('SKU-1');
        $cat = $this->makeCategory('cat-tv');
        $this->assign($product, $cat, true, 0);
        $channel = $this->makeChannel('allegro');
        $node = $this->makeNode($channel, 'rtv');
        // Pre-existing AUTO placement, but no mapping now references this channel.
        $this->placements()->upsert($product->getId(), $channel, $node, ChannelPlacementSource::Auto);

        $this->reconciler()->reconcile($product->getId(), $this->tenant->getId());

        self::assertNull(
            $this->placements()->findByObjectAndChannel($product->getId(), $channel->getId()),
            'stale auto placement removed',
        );
    }

    #[Test]
    public function backfillReconcilesEveryProductInTheMasterCategory(): void
    {
        $cat = $this->makeCategory('cat-tv');
        $channel = $this->makeChannel('allegro');
        $node = $this->makeNode($channel, 'rtv');
        $productA = $this->makeProduct('SKU-A');
        $productB = $this->makeProduct('SKU-B');
        $this->assign($productA, $cat, true, 0);
        $this->assign($productB, $cat, true, 0);
        $this->mapMaster($channel, $cat, [$node]);

        // Back-fill: both products already in the category get placements.
        $this->backfillHandler()(new ReconcileChannelPlacementsForCategory(
            $cat->getId()->toRfc4122(),
            $this->tenant->getId()->toRfc4122(),
        ));

        self::assertNotNull($this->placements()->findByObjectAndChannel($productA->getId(), $channel->getId()));
        self::assertNotNull($this->placements()->findByObjectAndChannel($productB->getId(), $channel->getId()));
    }

    private function assign(CatalogObject $product, CatalogObject $category, bool $isPrimary, int $position): void
    {
        $this->em()->persist(new ObjectCategory($product, $category, $isPrimary, $position));
        $this->em()->flush();
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
        $channel = new Channel($code, $code);
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

    private function reconciler(): ReconcileObjectChannelPlacements
    {
        return self::getContainer()->get(ReconcileObjectChannelPlacements::class);
    }

    private function backfillHandler(): ReconcileChannelPlacementsForCategoryHandler
    {
        return self::getContainer()->get(ReconcileChannelPlacementsForCategoryHandler::class);
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
