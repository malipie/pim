<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Channel\Contracts\ChannelResolverInterface;
use App\Shared\Domain\Tenant;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Overlays per-locale / per-channel {@see \App\Catalog\Domain\Entity\ObjectValue}
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
    ) {
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

        // Per attribute, keep the most specific applicable row above global.
        $best = [];
        foreach ($this->values->findByObject($object) as $value) {
            $valueLocale = $value->getLocale();
            $valueChannel = $value->getChannelId();

            $localeMatches = null === $valueLocale || (null !== $effectiveLocale && $valueLocale === $effectiveLocale);
            $channelMatches = null === $valueChannel || (null !== $channelId && $valueChannel->equals($channelId));
            if (!$localeMatches || !$channelMatches) {
                continue;
            }

            $rank = (null !== $valueLocale ? 2 : 0) + (null !== $valueChannel ? 1 : 0);
            if (0 === $rank) {
                // Global row — already the cache base.
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
