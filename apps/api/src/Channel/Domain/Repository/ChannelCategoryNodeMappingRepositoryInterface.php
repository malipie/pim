<?php

declare(strict_types=1);

namespace App\Channel\Domain\Repository;

use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelCategoryNodeMapping;
use Symfony\Component\Uid\Uuid;

interface ChannelCategoryNodeMappingRepositoryInterface
{
    /**
     * @return list<ChannelCategoryNodeMapping>
     */
    public function findByChannel(Channel $channel): array;

    public function findByChannelAndMaster(Channel $channel, Uuid $masterCategoryId): ?ChannelCategoryNodeMapping;

    /**
     * Create the (channel, master) mapping or replace its node set.
     *
     * @param list<string> $channelNodeIds
     */
    public function upsert(Channel $channel, Uuid $masterCategoryId, array $channelNodeIds): ChannelCategoryNodeMapping;

    public function save(ChannelCategoryNodeMapping $mapping): void;

    public function remove(ChannelCategoryNodeMapping $mapping): void;
}
