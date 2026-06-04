<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\ObjectValueLocaleOverlay;
use App\Catalog\Application\SystemAttributeReadOverlay;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Channel\Contracts\ChannelPublicationResolverInterface;
use App\Shared\Domain\Tenant;
use ArrayIterator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Traversable;

use const ARRAY_FILTER_USE_KEY;

/**
 * GetCollection provider for catalog sugar paths (`/api/products`,
 * `/api/categories`, `/api/objects`). Delegates loading to the default
 * Doctrine collection provider, then — when the request carries
 * `?locale=<code>` and/or `?channel=<code>` — overlays per-locale /
 * per-channel attribute readings before normalization (#1223 / T4).
 *
 * N+1 prevention: instead of calling `findByObject()` per item, the
 * provider issues one `findByObjectIds()` batch query for the entire page,
 * then passes the pre-loaded map to `ObjectValueLocaleOverlay::applyBatch()`.
 *
 * @implements ProviderInterface<CatalogObject>
 */
final readonly class CatalogObjectCollectionLocaleOverlayProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<object> $collectionProvider the default Doctrine ORM collection provider
     */
    public function __construct(
        private ProviderInterface $collectionProvider,
        private ObjectValueLocaleOverlay $overlay,
        private SystemAttributeReadOverlay $systemOverlay,
        private ObjectValueRepositoryInterface $objectValues,
        private RequestStack $requestStack,
        private ChannelPublicationResolverInterface $publicationResolver,
    ) {
    }

    /**
     * @return iterable<CatalogObject>|CatalogObject|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|iterable|null
    {
        $result = $this->collectionProvider->provide($operation, $uriVariables, $context);

        $request = $this->requestStack->getCurrentRequest();
        $localeParam = $request?->query->get('locale');
        $channelParam = $request?->query->get('channel');
        $publicationParam = $request?->query->get('publication');
        $locale = \is_string($localeParam) && '' !== $localeParam ? $localeParam : null;
        $channel = \is_string($channelParam) && '' !== $channelParam ? $channelParam : null;
        $publication = \is_string($publicationParam) && '' !== $publicationParam ? $publicationParam : null;

        // Collect CatalogObject items from the paginator or array result.
        // API Platform may return an iterable paginator or a plain array.
        $objects = [];
        if (\is_array($result)) {
            foreach ($result as $item) {
                if ($item instanceof CatalogObject) {
                    $objects[] = $item;
                }
            }
        } elseif ($result instanceof Traversable) {
            foreach ($result as $item) {
                if ($item instanceof CatalogObject) {
                    $objects[] = $item;
                }
            }
        }

        if ([] === $objects) {
            return $result; // @phpstan-ignore-line (inner provider return type is wider)
        }

        // Always apply system overlay so created_at/updated_at render.
        // When no locale/channel scope and no publication filter, skip overlay.
        if (null === $locale && null === $channel && null === $publication) {
            foreach ($objects as $object) {
                $this->systemOverlay->apply($object);
            }

            return $result; // @phpstan-ignore-line (inner provider return type is wider)
        }

        // Batch-load all ObjectValue rows for the current page in one query.
        $objectIds = array_map(
            static fn (CatalogObject $o): Uuid => $o->getId(),
            $objects,
        );
        $valuesByObjectId = $this->objectValues->findByObjectIds($objectIds);

        // Apply locale/channel overlay on clones (no identity map mutation).
        $overlaid = (null !== $locale || null !== $channel)
            ? $this->overlay->applyBatch($objects, $valuesByObjectId, $locale, $channel)
            : array_map(static fn (CatalogObject $o): CatalogObject => clone $o, $objects);

        // #1234 — ?publication=<channelCode> filters attributes_indexed per
        // publication profile. Applied after value overlay.
        if (null !== $publication) {
            $overlaid = $this->applyPublicationFilter($overlaid, $publication);
        }

        // Apply system overlay on the (already cloned) overlaid objects.
        $overlaid = array_map(
            fn (CatalogObject $o): CatalogObject => $this->systemOverlay->apply($o),
            $overlaid,
        );

        // Re-build the result preserving the paginator wrapper when present.
        if (\is_array($result)) {
            return $overlaid;
        }

        // For AP4 paginator results, wrap the overlaid items in a
        // TraversablePaginator so the serializer still gets correct
        // total-count / current-page metadata from the original paginator.
        if ($result instanceof PaginatorInterface) {
            return new TraversablePaginator(
                new ArrayIterator($overlaid),
                $result->getCurrentPage(),
                $result->getItemsPerPage(),
                $result->getTotalItems(),
            );
        }

        return $overlaid;
    }

    /**
     * Filters attributes_indexed to the publication allow-list for each object.
     * Objects are already cloned; mutates the clone in-place.
     *
     * @param list<CatalogObject> $objects
     *
     * @return list<CatalogObject>
     */
    private function applyPublicationFilter(array $objects, string $channelCode): array
    {
        foreach ($objects as $object) {
            $tenant = $object->getTenant();
            if (!$tenant instanceof Tenant) {
                continue;
            }
            $allowedCodes = $this->publicationResolver->resolvePublishedCodes(
                $channelCode,
                $object->getObjectType()->getId(),
                $tenant,
            );
            if (null === $allowedCodes) {
                continue;
            }
            $filtered = array_filter(
                $object->getAttributesIndexed(),
                static fn (string $code): bool => \in_array($code, $allowedCodes, true),
                ARRAY_FILTER_USE_KEY,
            );
            $object->updateAttributeIndex($filtered);
        }

        return $objects;
    }
}
