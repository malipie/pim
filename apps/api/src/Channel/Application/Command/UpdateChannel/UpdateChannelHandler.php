<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\UpdateChannel;

use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use App\Channel\Domain\Repository\LocaleRepositoryInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
final readonly class UpdateChannelHandler
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
        private LocaleRepositoryInterface $locales,
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

        if (null !== $command->label) {
            $channel->rename($command->label);
        }

        if (null !== $command->localeCodes) {
            // Remove all existing, then re-add desired set (idempotent + simple)
            foreach ($channel->getLocales() as $locale) {
                $channel->removeLocale($locale);
            }
            foreach ($command->localeCodes as $code) {
                $locale = $this->locales->findByCode($code);
                if (null === $locale) {
                    throw new UnprocessableEntityHttpException(\sprintf(
                        'Locale "%s" does not exist.',
                        $code,
                    ));
                }
                $channel->addLocale($locale);
            }
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
