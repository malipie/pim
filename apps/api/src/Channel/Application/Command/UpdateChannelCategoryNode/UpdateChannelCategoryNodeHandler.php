<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\UpdateChannelCategoryNode;

use App\Channel\Domain\Repository\ChannelCategoryNodeRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class UpdateChannelCategoryNodeHandler
{
    public function __construct(
        private ChannelCategoryNodeRepositoryInterface $nodes,
    ) {
    }

    public function __invoke(UpdateChannelCategoryNodeCommand $command): void
    {
        $node = $this->nodes->findById($command->nodeId);
        if (null === $node || !$node->getChannel()->getId()->equals($command->channelId)) {
            throw new NotFoundHttpException(\sprintf(
                'Navigation node "%s" was not found in this channel.',
                $command->nodeId->toRfc4122(),
            ));
        }

        if (null !== $command->label) {
            $node->rename($command->label);
        }

        if ($command->changeExternalCode) {
            $node->changeExternalCode($command->externalCode);
        }

        $this->nodes->save($node);
    }
}
