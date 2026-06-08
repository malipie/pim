<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\UpdateChannel;

use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class UpdateChannelHandler
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
    ) {
    }

    public function __invoke(UpdateChannelCommand $command): void
    {
        $channel = $this->channels->findById($command->id);
        if (null === $channel) {
            throw new NotFoundHttpException(\sprintf(
                'Channel "%s" was not found.',
                $command->id->toRfc4122(),
            ));
        }

        if (null !== $command->name) {
            $channel->rename($command->name);
        }

        if (false !== $command->categoryTreeRootId) {
            $rootId = (null === $command->categoryTreeRootId || '' === $command->categoryTreeRootId)
                ? null
                : Uuid::fromString($command->categoryTreeRootId);
            $channel->attachCategoryTreeRoot($rootId);
        }

        $this->channels->save($channel);
    }
}
