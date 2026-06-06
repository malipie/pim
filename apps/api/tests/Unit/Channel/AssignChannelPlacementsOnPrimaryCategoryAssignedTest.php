<?php

declare(strict_types=1);

namespace App\Tests\Unit\Channel;

use App\Catalog\Contracts\Event\ObjectPrimaryCategoryAssigned;
use App\Channel\Application\Subscriber\AssignChannelPlacementsOnPrimaryCategoryAssigned;
use App\Channel\Domain\ChannelPlacementSource;
use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelCategoryNode;
use App\Channel\Domain\Entity\ChannelCategoryNodeMapping;
use App\Channel\Domain\Entity\ObjectChannelPlacement;
use App\Channel\Domain\Repository\ChannelCategoryNodeMappingRepositoryInterface;
use App\Channel\Domain\Repository\ChannelCategoryNodeRepositoryInterface;
use App\Channel\Domain\Repository\ObjectChannelPlacementRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * CHC-07 (#1290) — unit coverage for placement auto-assignment from node
 * mappings. The read repositories are stubs; the placement repository is a
 * mock so the manual-wins rule and the fan-out are verified by call
 * expectations.
 */
final class AssignChannelPlacementsOnPrimaryCategoryAssignedTest extends TestCase
{
    private Uuid $objectId;
    private Uuid $masterCategoryId;
    private Channel $channel;
    private ChannelCategoryNode $node;

    protected function setUp(): void
    {
        $this->objectId = Uuid::v7();
        $this->masterCategoryId = Uuid::v7();
        $this->channel = new Channel('allegro', ['pl' => 'Allegro']);
        $this->node = new ChannelCategoryNode($this->channel, 'rtv', ['pl' => 'RTV']);
    }

    #[Test]
    public function upsertsAutoPlacementForEachMappedChannel(): void
    {
        $placements = $this->placementsMock(existing: null);
        $placements->expects(self::once())->method('upsert')
            ->with($this->objectId, $this->channel, $this->node, ChannelPlacementSource::Auto);

        $this->handler($this->mappingTo($this->node), $this->nodesReturn($this->node), $placements)($this->event());
    }

    #[Test]
    public function neverOverwritesManualPlacement(): void
    {
        $manual = new ObjectChannelPlacement($this->objectId, $this->channel, $this->node, ChannelPlacementSource::Manual);
        $placements = $this->placementsMock(existing: $manual);
        $placements->expects(self::never())->method('upsert');

        $this->handler($this->mappingTo($this->node), $this->nodesReturn($this->node), $placements)($this->event());
    }

    #[Test]
    public function repointsExistingAutoPlacement(): void
    {
        $auto = new ObjectChannelPlacement($this->objectId, $this->channel, $this->node, ChannelPlacementSource::Auto);
        $placements = $this->placementsMock(existing: $auto);
        $placements->expects(self::once())->method('upsert')
            ->with($this->objectId, $this->channel, $this->node, ChannelPlacementSource::Auto);

        $this->handler($this->mappingTo($this->node), $this->nodesReturn($this->node), $placements)($this->event());
    }

    #[Test]
    public function skipsMappingWithoutNodes(): void
    {
        $placements = $this->placementsMock(existing: null);
        $placements->expects(self::never())->method('upsert');

        $mappings = $this->createStub(ChannelCategoryNodeMappingRepositoryInterface::class);
        $mappings->method('findByMasterCategory')
            ->willReturn([new ChannelCategoryNodeMapping($this->channel, $this->masterCategoryId, [])]);

        $this->handler($mappings, $this->nodesReturn(null), $placements)($this->event());
    }

    #[Test]
    public function skipsStaleNodeReference(): void
    {
        $placements = $this->placementsMock(existing: null);
        $placements->expects(self::never())->method('upsert');

        $this->handler($this->mappingTo($this->node), $this->nodesReturn(null), $placements)($this->event());
    }

    #[Test]
    public function doesNothingWhenCategoryHasNoMappings(): void
    {
        $placements = $this->placementsMock(existing: null);
        $placements->expects(self::never())->method('upsert');

        $mappings = $this->createStub(ChannelCategoryNodeMappingRepositoryInterface::class);
        $mappings->method('findByMasterCategory')->willReturn([]);

        $this->handler($mappings, $this->nodesReturn(null), $placements)($this->event());
    }

    private function mappingTo(ChannelCategoryNode $node): ChannelCategoryNodeMappingRepositoryInterface
    {
        $mappings = $this->createStub(ChannelCategoryNodeMappingRepositoryInterface::class);
        $mappings->method('findByMasterCategory')
            ->willReturn([new ChannelCategoryNodeMapping($this->channel, $this->masterCategoryId, [$node->getId()->toRfc4122()])]);

        return $mappings;
    }

    private function nodesReturn(?ChannelCategoryNode $node): ChannelCategoryNodeRepositoryInterface
    {
        $nodes = $this->createStub(ChannelCategoryNodeRepositoryInterface::class);
        $nodes->method('findById')->willReturn($node);

        return $nodes;
    }

    private function placementsMock(?ObjectChannelPlacement $existing): ObjectChannelPlacementRepositoryInterface&MockObject
    {
        $placements = $this->createMock(ObjectChannelPlacementRepositoryInterface::class);
        $placements->method('findByObjectAndChannel')->willReturn($existing);

        return $placements;
    }

    private function handler(
        ChannelCategoryNodeMappingRepositoryInterface $mappings,
        ChannelCategoryNodeRepositoryInterface $nodes,
        ObjectChannelPlacementRepositoryInterface $placements,
    ): AssignChannelPlacementsOnPrimaryCategoryAssigned {
        return new AssignChannelPlacementsOnPrimaryCategoryAssigned($mappings, $nodes, $placements);
    }

    private function event(): ObjectPrimaryCategoryAssigned
    {
        return new ObjectPrimaryCategoryAssigned($this->objectId, Uuid::v7(), $this->masterCategoryId);
    }
}
