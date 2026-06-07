<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\CreateNavigationTreeRoot;

use App\Channel\Domain\Entity\ChannelCategoryNode;
use App\Channel\Domain\Repository\ChannelCategoryNodeRepositoryInterface;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
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

        // A channel category tree is a forest: multiple top-level ("root")
        // categories are allowed (RTV, Moda, ...). The single-root assumption
        // was a bug — we no longer reject a second root.
        $isFirstRoot = null === $this->nodes->findRootForChannel($channel);

        // The editor sends only name + externalId; default an absent code to the
        // root's uuid-hex so it is unique per channel (a channel may have many
        // roots — no fixed 'root' slug that would collide).
        $id = Uuid::v7();
        $code = (null === $command->code || '' === trim($command->code))
            ? str_replace('-', '', $id->toRfc4122())
            : $command->code;

        $root = new ChannelCategoryNode(
            channel: $channel,
            code: $code,
            label: $command->label,
            parent: null,
            externalCode: $command->externalCode,
            id: $id,
        );
        $root->attachToPath($root->ltreeLabel());
        $this->nodes->save($root);

        // `categoryTreeRootId` is a legacy soft pointer kept only for the first
        // root (backward-compat + validator). Subsequent roots leave it intact.
        if ($isFirstRoot) {
            $channel->attachCategoryTreeRoot($root->getId());
            $this->channels->save($channel);
        }

        return $root->getId();
    }
}
