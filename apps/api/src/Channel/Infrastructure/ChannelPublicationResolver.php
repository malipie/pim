<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure;

use App\Channel\Contracts\ChannelPublicationResolverInterface;
use App\Channel\Domain\Repository\ChannelPublicationProfileRepositoryInterface;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

/**
 * Resolves a channel's publication profile for cross-BC consumers.
 *
 * Falls back to publish-all (null) when no profile row exists for the
 * given (channelCode, objectTypeId, tenant) triple — providing zero-config
 * behaviour for tenants that haven't configured explicit profiles.
 *
 * Autowiring aliases {@see ChannelPublicationResolverInterface} to this impl.
 */
final readonly class ChannelPublicationResolver implements ChannelPublicationResolverInterface
{
    public function __construct(
        private ChannelRepositoryInterface $channels,
        private ChannelPublicationProfileRepositoryInterface $profiles,
    ) {
    }

    /**
     * @return list<string>|null null = publish-all
     */
    public function resolvePublishedCodes(
        string $channelCode,
        Uuid $objectTypeId,
        Tenant $tenant,
    ): ?array {
        $channel = $this->channels->findByCode($channelCode, $tenant);
        if (null === $channel) {
            return null;
        }
        $profile = $this->profiles->findByChannelAndObjectType($channel->getId(), $objectTypeId, $tenant);
        if (null === $profile) {
            return null;
        }

        return $profile->getPublishedAttributeCodes();
    }

    /**
     * @return list<string>
     */
    public function resolvePublishedLocales(string $channelCode, Tenant $tenant): array
    {
        $channel = $this->channels->findByCode($channelCode, $tenant);
        if (null === $channel) {
            return [];
        }

        $profiles = $this->profiles->findForChannel($channel->getId(), $tenant);
        if ([] === $profiles) {
            return [];
        }

        // Collect published locales across all profiles for this channel.
        // If any profile has an empty published_locales, it signals "use tenant locales".
        $locales = [];
        foreach ($profiles as $profile) {
            foreach ($profile->getPublishedLocales() as $locale) {
                $locales[$locale] = true;
            }
        }

        return array_keys($locales);
    }
}
