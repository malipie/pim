<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\DeleteChannelCategoryNode;

use App\Channel\Domain\Repository\ChannelCategoryNodeRepositoryInterface;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteChannelCategoryNodeHandler
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
        private ChannelCategoryNodeRepositoryInterface $nodes,
    ) {
    }

    public function __invoke(DeleteChannelCategoryNodeCommand $command): void
    {
        $node = $this->nodes->findById($command->nodeId);
        if (null === $node || !$node->getChannel()->getId()->equals($command->channelId)) {
            throw new NotFoundHttpException(\sprintf(
                'Navigation node "%s" was not found in this channel.',
                $command->nodeId->toRfc4122(),
            ));
        }

        $channel = $node->getChannel();
        $wasRoot = $node->isRoot();

        // Descendants are removed by the `parent_id ON DELETE CASCADE` FK.
        $this->nodes->remove($node);

        if ($wasRoot) {
            $channel->attachCategoryTreeRoot(null);
            $this->channels->save($channel);
        }
    }
}
