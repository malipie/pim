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
use ArrayIterator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Traversable;

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
        $locale = \is_string($localeParam) && '' !== $localeParam ? $localeParam : null;
        $channel = \is_string($channelParam) && '' !== $channelParam ? $channelParam : null;

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
        // When no locale/channel scope is active, skip the value overlay.
        if (null === $locale && null === $channel) {
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
        $overlaid = $this->overlay->applyBatch($objects, $valuesByObjectId, $locale, $channel);

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
}
