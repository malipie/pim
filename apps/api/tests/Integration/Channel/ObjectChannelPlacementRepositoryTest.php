<?php

declare(strict_types=1);

namespace App\Tests\Integration\Channel;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Channel\Domain\ChannelPlacementSource;
use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelCategoryNode;
use App\Channel\Domain\Repository\ObjectChannelPlacementRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * CHC-02 (#1285) — ObjectChannelPlacement repository behaviour.
 */
final class ObjectChannelPlacementRepositoryTest extends KernelTestCase
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
    public function upsertCreatesPlacement(): void
    {
        $product = $this->makeProduct('SKU-1');
        $channel = $this->makeChannel('allegro');
        $node = $this->makeRoot($channel);

        $placement = $this->repo()->upsert($product->getId(), $channel, $node, ChannelPlacementSource::Manual);

        self::assertTrue($placement->getObjectId()->equals($product->getId()));
        self::assertTrue($placement->getNodeId()->equals($node->getId()));
        self::assertSame(ChannelPlacementSource::Manual, $placement->getSource());

        $found = $this->repo()->findByObjectAndChannel($product->getId(), $channel->getId());
        self::assertNotNull($found);
        self::assertTrue($found->getId()->equals($placement->getId()));
    }

    #[Test]
    public function upsertOnSameObjectAndChannelUpdatesWithoutDuplicating(): void
    {
        $product = $this->makeProduct('SKU-1');
        $channel = $this->makeChannel('allegro');
        $root = $this->makeRoot($channel);
        $child = $this->makeChild($channel, $root, 'telewizory');

        $this->repo()->upsert($product->getId(), $channel, $root, ChannelPlacementSource::Manual);
        $this->repo()->upsert($product->getId(), $channel, $child, ChannelPlacementSource::Auto);

        $all = $this->repo()->findByObject($product->getId());
        self::assertCount(1, $all);
        self::assertTrue($all[0]->getNodeId()->equals($child->getId()));
        self::assertSame(ChannelPlacementSource::Auto, $all[0]->getSource());
    }

    #[Test]
    public function findByObjectReturnsEveryChannelPlacement(): void
    {
        $product = $this->makeProduct('SKU-1');
        $allegro = $this->makeChannel('allegro');
        $shopify = $this->makeChannel('shopify');

        $this->repo()->upsert($product->getId(), $allegro, $this->makeRoot($allegro), ChannelPlacementSource::Manual);
        $this->repo()->upsert($product->getId(), $shopify, $this->makeRoot($shopify), ChannelPlacementSource::Manual);

        self::assertCount(2, $this->repo()->findByObject($product->getId()));
    }

    #[Test]
    public function removeDeletesPlacement(): void
    {
        $product = $this->makeProduct('SKU-1');
        $channel = $this->makeChannel('allegro');
        $placement = $this->repo()->upsert($product->getId(), $channel, $this->makeRoot($channel), ChannelPlacementSource::Manual);

        $this->repo()->remove($placement);

        self::assertNull($this->repo()->findByObjectAndChannel($product->getId(), $channel->getId()));
        self::assertCount(0, $this->repo()->findByObject($product->getId()));
    }

    private function repo(): ObjectChannelPlacementRepositoryInterface
    {
        return self::getContainer()->get(ObjectChannelPlacementRepositoryInterface::class);
    }

    private function makeProduct(string $sku): CatalogObject
    {
        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $this->tenant);
        \assert(null !== $type);
        $product = new CatalogObject($type, $sku);
        $em = $this->em();
        $em->persist($product);
        $em->flush();

        return $product;
    }

    private function makeChannel(string $code): Channel
    {
        $channel = new Channel($code, $code);
        $em = $this->em();
        $em->persist($channel);
        $em->flush();

        return $channel;
    }

    private function makeRoot(Channel $channel): ChannelCategoryNode
    {
        $node = new ChannelCategoryNode($channel, 'root', ['pl' => 'Root']);
        $node->attachToPath($node->ltreeLabel());
        $em = $this->em();
        $em->persist($node);
        $em->flush();

        return $node;
    }

    private function makeChild(Channel $channel, ChannelCategoryNode $parent, string $code): ChannelCategoryNode
    {
        $node = new ChannelCategoryNode($channel, $code, ['pl' => $code], $parent);
        $node->attachToPath($parent->getPath().'.'.$node->ltreeLabel());
        $em = $this->em();
        $em->persist($node);
        $em->flush();

        return $node;
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
