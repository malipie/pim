<?php

declare(strict_types=1);

namespace App\Channel\Domain\Repository;

use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelCategoryNode;
use Symfony\Component\Uid\Uuid;

interface ChannelCategoryNodeRepositoryInterface
{
    public function findById(Uuid $id): ?ChannelCategoryNode;

    public function findRootForChannel(Channel $channel): ?ChannelCategoryNode;

    /**
     * Flat list of all nodes for the channel, ordered by `path` so the caller
     * can render the tree without recursion.
     *
     * @return list<ChannelCategoryNode>
     */
    public function findAllForChannel(Channel $channel): array;

    public function save(ChannelCategoryNode $node): void;

    public function remove(ChannelCategoryNode $node): void;
}
