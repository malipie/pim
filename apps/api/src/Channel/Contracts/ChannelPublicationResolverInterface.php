<?php

declare(strict_types=1);

namespace App\Channel\Contracts;

use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

/**
 * Cross-BC interface for reading a channel's publication profile.
 *
 * Catalog and Export BCs call this to filter which attributes/locales to
 * expose when a consumer provides `?publication=<channelCode>`. ADR-0018.
 *
 * Implementations are in `Channel\Infrastructure`; Catalog and Export only
 * import this interface (Deptrac-safe: Channel_Contracts is allowed from
 * both Catalog_Internals and Export_Internals).
 */
interface ChannelPublicationResolverInterface
{
    /**
     * Returns the allow-list of attribute codes for the given channel +
     * objectType combination. `null` means publish-all (no filtering).
     *
     * @return list<string>|null null = publish-all; [] = publish-nothing
     */
    public function resolvePublishedCodes(
        string $channelCode,
        Uuid $objectTypeId,
        Tenant $tenant,
    ): ?array;

    /**
     * Returns the list of short locale codes configured for the channel
     * profile. Empty array means "use tenant locales" (no filtering).
     *
     * @return list<string>
     */
    public function resolvePublishedLocales(
        string $channelCode,
        Tenant $tenant,
    ): array;
}
