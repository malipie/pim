<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\CreateChannel;

use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use App\Shared\Application\TenantContext;
use LogicException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class CreateChannelHandler
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
        private TenantContext $tenantContext,
    ) {
    }

    public function __invoke(CreateChannelCommand $command): Uuid
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new LogicException('Cannot create Channel without an authenticated tenant.');
        }

        if (null !== $this->channels->findByCode($command->code, $tenant)) {
            throw new ConflictHttpException(\sprintf(
                'Channel with code "%s" already exists for this tenant.',
                $command->code,
            ));
        }

        $channel = new Channel(code: $command->code, name: $command->name);

        if (null !== $command->categoryTreeRootId && '' !== $command->categoryTreeRootId) {
            $channel->attachCategoryTreeRoot(Uuid::fromString($command->categoryTreeRootId));
        }

        $this->channels->save($channel);

        return $channel->getId();
    }
}
