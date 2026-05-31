<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\ObjectValueLocaleOverlay;
use App\Catalog\Domain\Entity\CatalogObject;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * GET-item provider for the catalog sugar paths (`/api/products/{id}`,
 * `/api/categories/{id}`, `/api/objects/{id}`). Delegates loading to the
 * default Doctrine item provider, then — when the request carries
 * `?locale=<code>` — overlays the per-locale attribute readings before
 * normalization (#1146 / #1148).
 *
 * Keeping the overlay in the read provider (not a serializer hack) means
 * the admin's existing `unwrapAttributesIndexed` / `fieldValue` flow reads
 * locale-resolved values with zero frontend change beyond appending the
 * query param.
 *
 * @implements ProviderInterface<CatalogObject>
 */
final readonly class CatalogObjectLocaleOverlayProvider implements ProviderInterface
{
    /**
     * @param ProviderInterface<CatalogObject> $itemProvider the default Doctrine ORM item provider
     */
    public function __construct(
        private ProviderInterface $itemProvider,
        private ObjectValueLocaleOverlay $overlay,
        private RequestStack $requestStack,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CatalogObject
    {
        $object = $this->itemProvider->provide($operation, $uriVariables, $context);
        if (!$object instanceof CatalogObject) {
            // Item GET resolves to a single CatalogObject or null (not found).
            return null;
        }

        $request = $this->requestStack->getCurrentRequest();
        $localeParam = $request?->query->get('locale');
        $channelParam = $request?->query->get('channel');
        $locale = \is_string($localeParam) && '' !== $localeParam ? $localeParam : null;
        $channel = \is_string($channelParam) && '' !== $channelParam ? $channelParam : null;
        if (null !== $locale || null !== $channel) {
            return $this->overlay->apply($object, $locale, $channel);
        }

        return $object;
    }
}
