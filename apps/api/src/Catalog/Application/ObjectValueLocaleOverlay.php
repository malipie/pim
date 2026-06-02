<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Channel\Application\Locale\LocaleFallbackResolver;
use App\Channel\Contracts\ChannelResolverInterface;
use App\Shared\Domain\Tenant;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Overlays per-locale / per-channel {@see ObjectValue}
 * rows on top of an object's global `attributes_indexed` cache so a scoped
 * read (`?locale=en`, `?channel=shopify`, or both) returns the most
 * specific reading (#1146 / #1148 locale; #1147 / #1154 channel).
 *
 * Contract:
 *   - GLOBAL row = `locale=null, channel=null`; it lives in the cache and
 *     is the fallback. The tenant's primary locale is stored as global, so
 *     a read in the primary locale needs no locale overlay.
 *   - Per (effective locale, channel) the most specific applicable row
 *     wins, falling back down the chain
 *     `(locale+channel) > channel-only > locale-only > global`.
 *
 * Returns a detached CLONE with the overlaid index — never the managed
 * entity. Mutating the managed instance would leak the scoped reading onto
 * the identity map and corrupt a later read of the same object in the same
 * EntityManager. The clone shares the entity's relation references so
 * serialization + the READ voter keep working; only the scalar
 * `attributes_indexed` array (copied by value on clone) is overlaid.
 */
final readonly class ObjectValueLocaleOverlay
{
    public function __construct(
        private ObjectValueRepositoryInterface $values,
        private ChannelResolverInterface $channels,
        private LocaleFallbackResolver $localeFallback,
    ) {
    }

    /**
     * Batch variant of {@see apply()} for collection reads.
     *
     * Accepts a pre-loaded map of ObjectValue rows (keyed by object UUID) so
     * the caller can issue a single `findByObjectIds()` query instead of one
     * `findByObject()` call per item — preventing N+1 on list endpoints.
     *
     * @param list<CatalogObject>              $objects
     * @param array<string, list<ObjectValue>> $valuesByObjectId keyed by UUID RFC 4122
     *
     * @return list<CatalogObject>
     */
    public function applyBatch(array $objects, array $valuesByObjectId, ?string $locale, ?string $channelCode = null): array
    {
        if ([] === $objects) {
            return [];
        }

        // All objects share the same tenant in a single-tenant request.
        $tenant = $objects[0]->getTenant();
        if (!$tenant instanceof Tenant) {
            return $objects;
        }

        if (null !== $locale && !$tenant->isLocaleEnabled($locale)) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Locale "%s" is not enabled for this tenant.',
                $locale,
            ));
        }
        $effectiveLocale = null !== $locale && $locale !== $tenant->getPrimaryLocale() ? $locale : null;

        $channelId = null;
        if (null !== $channelCode) {
            $channelId = $this->channels->resolveId($channelCode, $tenant);
            if (null === $channelId) {
                throw new UnprocessableEntityHttpException(\sprintf(
                    'Channel "%s" was not found for this tenant.',
                    $channelCode,
                ));
            }
        }

        if (null === $effectiveLocale && null === $channelId) {
            return $objects;
        }

        $localeChain = null !== $effectiveLocale
            ? $this->localeFallback->resolve($effectiveLocale, $tenant)
            : [];
        $localeRankMap = [];
        foreach ($localeChain as $pos => $chainCode) {
            $localeRankMap[$chainCode] = $pos;
        }
        $maxChainLen = \count($localeChain);

        $result = [];
        foreach ($objects as $object) {
            $objectKey = $object->getId()->toRfc4122();
            $rows = $valuesByObjectId[$objectKey] ?? [];

            $best = [];
            foreach ($rows as $value) {
                $valueLocale = $value->getLocale();
                $valueChannel = $value->getChannelId();

                if (null !== $valueLocale) {
                    if (!isset($localeRankMap[$valueLocale])) {
                        continue;
                    }
                    $localeRankContrib = ($maxChainLen - $localeRankMap[$valueLocale]) * 2;
                } else {
                    $localeRankContrib = 0;
                }

                if (null !== $valueChannel) {
                    if (null === $channelId || !$valueChannel->equals($channelId)) {
                        continue;
                    }
                    $channelRankContrib = 1;
                } else {
                    $channelRankContrib = 0;
                }

                $rank = $localeRankContrib + $channelRankContrib;
                if (0 === $rank) {
                    continue;
                }

                $code = $value->getAttribute()->getCode();
                if (!isset($best[$code]) || $rank > $best[$code]['rank']) {
                    $best[$code] = ['rank' => $rank, 'value' => $value->getValue()];
                }
            }

            if ([] === $best) {
                $result[] = $object;
                continue;
            }

            $indexed = $object->getAttributesIndexed();
            foreach ($best as $code => $entry) {
                $indexed[$code] = $entry['value'];
            }
            $copy = clone $object;
            $copy->updateAttributeIndex($indexed);
            $result[] = $copy;
        }

        return $result;
    }

    public function apply(CatalogObject $object, ?string $locale, ?string $channelCode = null): CatalogObject
    {
        $tenant = $object->getTenant();
        if (!$tenant instanceof Tenant) {
            return $object;
        }

        if (null !== $locale && !$tenant->isLocaleEnabled($locale)) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'Locale "%s" is not enabled for this tenant.',
                $locale,
            ));
        }
        // Primary locale is stored as the global row, so it needs no
        // locale-specific overlay — treat it as "no locale scope".
        $effectiveLocale = null !== $locale && $locale !== $tenant->getPrimaryLocale() ? $locale : null;

        $channelId = null;
        if (null !== $channelCode) {
            $channelId = $this->channels->resolveId($channelCode, $tenant);
            if (null === $channelId) {
                throw new UnprocessableEntityHttpException(\sprintf(
                    'Channel "%s" was not found for this tenant.',
                    $channelCode,
                ));
            }
        }

        // No active scope → the global cache is already the right reading.
        if (null === $effectiveLocale && null === $channelId) {
            return $object;
        }

        // Build the locale fallback chain (most specific first).
        // When no locale scope is active the chain is empty and locale
        // matching degenerates to the channel-only / global path.
        $localeChain = null !== $effectiveLocale
            ? $this->localeFallback->resolve($effectiveLocale, $tenant)
            : [];
        // Index chain position for fast rank comparison: lower index = more specific.
        $localeRankMap = [];
        foreach ($localeChain as $pos => $chainCode) {
            $localeRankMap[$chainCode] = $pos;
        }

        // Per attribute, keep the most specific applicable row above global.
        // Rank ordering (higher = wins):
        //   (locale_chain_pos 0 + channel) > (locale_chain_pos 0) >
        //   (locale_chain_pos 1 + channel) > (locale_chain_pos 1) > … >
        //   (channel-only) > global (skipped — cache base)
        //
        // Encoded as: rank = (maxChainLen - chainPos) * 2 + hasChannel
        $maxChainLen = \count($localeChain);
        /** @var array<string, array{rank: int, value: mixed}> $best */
        $best = [];
        foreach ($this->values->findByObject($object) as $value) {
            $valueLocale = $value->getLocale();
            $valueChannel = $value->getChannelId();

            // Determine locale rank contribution.
            if (null !== $valueLocale) {
                if (!isset($localeRankMap[$valueLocale])) {
                    // This row belongs to a locale not in our chain — skip.
                    continue;
                }
                $localeRankContrib = ($maxChainLen - $localeRankMap[$valueLocale]) * 2;
            } else {
                // locale-null row: only relevant when there is a channel scope.
                $localeRankContrib = 0;
            }

            // Determine channel rank contribution.
            if (null !== $valueChannel) {
                if (null === $channelId || !$valueChannel->equals($channelId)) {
                    // Row is for a different channel — skip.
                    continue;
                }
                $channelRankContrib = 1;
            } else {
                $channelRankContrib = 0;
            }

            $rank = $localeRankContrib + $channelRankContrib;
            if (0 === $rank) {
                // Global row (locale=null, channel=null) — already the cache base.
                continue;
            }

            $code = $value->getAttribute()->getCode();
            if (!isset($best[$code]) || $rank > $best[$code]['rank']) {
                $best[$code] = ['rank' => $rank, 'value' => $value->getValue()];
            }
        }

        if ([] === $best) {
            return $object;
        }

        $indexed = $object->getAttributesIndexed();
        foreach ($best as $code => $entry) {
            $indexed[$code] = $entry['value'];
        }

        $copy = clone $object;
        $copy->updateAttributeIndex($indexed);

        return $copy;
    }
}
