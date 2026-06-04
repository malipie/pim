<?php

declare(strict_types=1);

namespace App\Export\Application\Builder;

use App\Channel\Contracts\ChannelPublicationResolverInterface;
use App\Export\Domain\Entity\ExportSession;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

/**
 * Generates export column keys from a channel's publication profile when
 * the operator hasn't manually picked columns.
 *
 * When `ExportSession.selectedColumns` is empty and the session carries at
 * least one channel, this service queries the publication profile of the
 * first session channel and expands it into a flat list of column keys
 * (`code`, `code.locale`, `code.channel`) that the {@see ColumnResolver}
 * understands. ADR-0018.
 *
 * Falls back to `null` (no generated columns) when:
 *   - The profile has no explicit allow-list (`publishedAttributeCodes = null`
 *     → publish-all; we can't enumerate "all attribute codes" without a DB
 *     query and a Catalog dependency).
 *   - The session has no channels configured.
 *   - The objectType ID is unknown.
 *
 * In these cases the caller should preserve existing behaviour (require
 * manual `selectedColumns` or throw).
 */
final readonly class PublicationColumnPlanner
{
    public function __construct(
        private ChannelPublicationResolverInterface $publicationResolver,
    ) {
    }

    /**
     * Returns generated column keys or null if the profile cannot provide
     * a definitive list.
     *
     * @param list<string> $objectTypeIds UUID strings of object types in the session
     *
     * @return list<string>|null null = can't generate (operator must supply selectedColumns)
     */
    public function plan(ExportSession $session, array $objectTypeIds): ?array
    {
        $tenant = $session->getTenant();
        if (!$tenant instanceof Tenant) {
            return null;
        }

        $sessionChannels = $session->getChannels() ?? [];
        if ([] === $sessionChannels) {
            return null;
        }

        // Use the first channel as the publication profile source.
        $channelCode = $sessionChannels[0];

        $sessionLocales = $session->getLocales() ?? [];

        $columns = [];
        foreach ($objectTypeIds as $rawId) {
            $objectTypeId = Uuid::fromString($rawId);
            $allowedCodes = $this->publicationResolver->resolvePublishedCodes(
                $channelCode,
                $objectTypeId,
                $tenant,
            );

            if (null === $allowedCodes) {
                // Publish-all → can't generate a finite column list.
                return null;
            }

            foreach ($allowedCodes as $code) {
                // Bare column (global value)
                $columns[] = $code;

                // Per-locale columns for each session locale.
                foreach ($sessionLocales as $locale) {
                    $columns[] = "{$code}.{$locale}";
                }

                // Per-channel columns for each session channel.
                foreach ($sessionChannels as $channel) {
                    $columns[] = "{$code}.{$channel}";
                }
            }
        }

        // De-duplicate while preserving order (bare code + qualified variants
        // share the same code prefix; duplicates appear when multiple OTs
        // share attribute codes).
        $seen = [];
        $unique = [];
        foreach ($columns as $col) {
            if (!isset($seen[$col])) {
                $seen[$col] = true;
                $unique[] = $col;
            }
        }

        return [] === $unique ? null : $unique;
    }
}
