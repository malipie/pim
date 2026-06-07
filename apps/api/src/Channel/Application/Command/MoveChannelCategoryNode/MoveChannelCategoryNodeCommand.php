<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\MoveChannelCategoryNode;

use Symfony\Component\Uid\Uuid;

/**
 * CHC-09 (#1302) — re-parent a channel navigation node under a different node,
 * rewriting the ltree path of the node and all its descendants.
 */
final readonly class MoveChannelCategoryNodeCommand
{
    public function __construct(
        public Uuid $channelId,
        public Uuid $nodeId,
        public Uuid $newParentId,
    ) {
    }
}
