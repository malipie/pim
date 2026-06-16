<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Application\AssetPreviewUrlReadOverlay;
use App\Catalog\Application\AttributeReadRestrictionOverlay;
use App\Catalog\Application\ObjectValueLocaleOverlay;
use App\Catalog\Application\SystemAttributeReadOverlay;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Channel\Contracts\ChannelPublicationResolverInterface;
use App\Shared\Domain\Tenant;
use Symfony\Component\HttpFoundation\RequestStack;

use const ARRAY_FILTER_USE_KEY;

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
        private SystemAttributeReadOverlay $systemOverlay,
        private AssetPreviewUrlReadOverlay $previewUrlOverlay,
        private RequestStack $requestStack,
        private ChannelPublicationResolverInterface $publicationResolver,
        private AttributeReadRestrictionOverlay $restrictionOverlay,
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
        $publicationParam = $request?->query->get('publication');
        $locale = \is_string($localeParam) && '' !== $localeParam ? $localeParam : null;
        $channel = \is_string($channelParam) && '' !== $channelParam ? $channelParam : null;
        $publication = \is_string($publicationParam) && '' !== $publicationParam ? $publicationParam : null;

        if (null !== $locale || null !== $channel) {
            $object = $this->overlay->apply($object, $locale, $channel);
        }

        // #1234 — ?publication=<channelCode> filters attributes_indexed to the
        // channel's publication allow-list. Applied after value overlay so the
        // returned values are already locale/channel-resolved.
        if (null !== $publication) {
            $object = $this->applyPublicationFilter($object, $publication);
        }

        // #1207 — always surface the system attributes (created_at/updated_at +
        // created_by/updated_by) so they render real values instead of "—".
        $object = $this->systemOverlay->apply($object);

        // AUD-006 (#1576) sign the `previewUrl` per-request, then AUD-008
        // (#1578) strip attributes the caller may not view (3-state
        // per-attribute permission, PRD §3.5). Restriction runs last so the
        // system attributes injected above are preserved (not permission
        // subjects) and only the short-lived signed previewUrl is handed out.
        return $this->restrictionOverlay->apply($this->previewUrlOverlay->apply($object));
    }

    private function applyPublicationFilter(CatalogObject $object, string $channelCode): CatalogObject
    {
        $tenant = $object->getTenant();
        if (!$tenant instanceof Tenant) {
            return $object;
        }

        $allowedCodes = $this->publicationResolver->resolvePublishedCodes(
            $channelCode,
            $object->getObjectType()->getId(),
            $tenant,
        );

        if (null === $allowedCodes) {
            return $object;
        }

        $filtered = array_filter(
            $object->getAttributesIndexed(),
            static fn (string $code): bool => \in_array($code, $allowedCodes, true),
            ARRAY_FILTER_USE_KEY,
        );

        $copy = clone $object;
        $copy->updateAttributeIndex($filtered);

        return $copy;
    }
}
