<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\DeleteChannelCategoryNode;

use Symfony\Component\Uid\Uuid;

final readonly class DeleteChannelCategoryNodeCommand
{
    public function __construct(
        public Uuid $channelId,
        public Uuid $nodeId,
    ) {
    }
}
