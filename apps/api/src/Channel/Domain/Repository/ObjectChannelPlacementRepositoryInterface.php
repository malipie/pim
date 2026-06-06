<?php

declare(strict_types=1);

namespace App\Channel\Domain\Repository;

use App\Channel\Domain\ChannelPlacementSource;
use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelCategoryNode;
use App\Channel\Domain\Entity\ObjectChannelPlacement;
use Symfony\Component\Uid\Uuid;

interface ObjectChannelPlacementRepositoryInterface
{
    /**
     * All placements of a product across every channel.
     *
     * @return list<ObjectChannelPlacement>
     */
    public function findByObject(Uuid $objectId): array;

    public function findByObjectAndChannel(Uuid $objectId, Uuid $channelId): ?ObjectChannelPlacement;

    /**
     * Create the (object, channel) placement or re-point an existing one.
     */
    public function upsert(
        Uuid $objectId,
        Channel $channel,
        ChannelCategoryNode $node,
        ChannelPlacementSource $source,
    ): ObjectChannelPlacement;

    public function save(ObjectChannelPlacement $placement): void;

    public function remove(ObjectChannelPlacement $placement): void;
}
