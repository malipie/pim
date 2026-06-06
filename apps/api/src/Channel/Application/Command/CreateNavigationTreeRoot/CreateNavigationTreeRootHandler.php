<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\CreateNavigationTreeRoot;

use App\Channel\Domain\Entity\ChannelCategoryNode;
use App\Channel\Domain\Repository\ChannelCategoryNodeRepositoryInterface;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class CreateNavigationTreeRootHandler
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
        private ChannelCategoryNodeRepositoryInterface $nodes,
    ) {
    }

    public function __invoke(CreateNavigationTreeRootCommand $command): Uuid
    {
        $channel = $this->channels->findById($command->channelId);
        if (null === $channel) {
            throw new NotFoundHttpException(\sprintf(
                'Channel "%s" was not found.',
                $command->channelId->toRfc4122(),
            ));
        }

        if (null !== $this->nodes->findRootForChannel($channel)) {
            throw new ConflictHttpException(\sprintf(
                'Channel "%s" already has a navigation tree.',
                $channel->getCode(),
            ));
        }

        $root = new ChannelCategoryNode(
            channel: $channel,
            code: $command->code,
            label: $command->label,
            parent: null,
            externalCode: $command->externalCode,
        );
        $root->attachToPath($root->ltreeLabel());
        $this->nodes->save($root);

        $channel->attachCategoryTreeRoot($root->getId());
        $this->channels->save($channel);

        return $root->getId();
    }
}
