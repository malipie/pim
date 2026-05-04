<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\DeleteChannel;

use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteChannelHandler
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
    ) {
    }

    public function __invoke(DeleteChannelCommand $command): void
    {
        $channel = $this->channels->findById($command->id);
        if (null === $channel) {
            throw new NotFoundHttpException(\sprintf(
                'Channel "%s" was not found.',
                $command->id->toRfc4122(),
            ));
        }

        $this->channels->remove($channel);
    }
}
