<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\AddChannelCategoryNode;

use App\Channel\Domain\Entity\ChannelCategoryNode;
use App\Channel\Domain\Repository\ChannelCategoryNodeRepositoryInterface;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class AddChannelCategoryNodeHandler
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
        private ChannelCategoryNodeRepositoryInterface $nodes,
    ) {
    }

    public function __invoke(AddChannelCategoryNodeCommand $command): Uuid
    {
        $channel = $this->channels->findById($command->channelId);
        if (null === $channel) {
            throw new NotFoundHttpException(\sprintf(
                'Channel "%s" was not found.',
                $command->channelId->toRfc4122(),
            ));
        }

        $parent = $this->nodes->findById($command->parentId);
        if (null === $parent || !$parent->getChannel()->getId()->equals($channel->getId())) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Parent node "%s" does not belong to channel "%s".',
                $command->parentId->toRfc4122(),
                $channel->getCode(),
            ));
        }

        // CHC-09: the tree editor sends only name + externalId, no code. Pre-generate
        // the node id so an absent code can default to its uuid-hex — guaranteed unique
        // per (tenant, channel) without the operator ever seeing a slug.
        $id = Uuid::v7();
        $code = (null === $command->code || '' === trim($command->code))
            ? str_replace('-', '', $id->toRfc4122())
            : $command->code;

        $node = new ChannelCategoryNode(
            channel: $channel,
            code: $code,
            label: $command->label,
            parent: $parent,
            externalCode: $command->externalCode,
            id: $id,
        );

        $parentPath = $parent->getPath();
        $prefix = (null === $parentPath || '' === $parentPath) ? '' : $parentPath.'.';
        $node->attachToPath($prefix.$node->ltreeLabel());

        $this->nodes->save($node);

        return $node->getId();
    }
}
