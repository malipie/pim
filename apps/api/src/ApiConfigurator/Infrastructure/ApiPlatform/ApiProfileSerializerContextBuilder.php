<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Infrastructure\ApiPlatform;

use ApiPlatform\State\SerializerContextBuilderInterface;
use App\ApiConfigurator\Application\ApiProfileResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decorator: injects per-profile serializer context for API-key
 * requests.
 *
 * When the request authenticates with `X-API-Key`, the resolved
 * {@see \App\ApiConfigurator\Domain\Entity\ApiProfile} dictates:
 *   - serializer groups (`public:read`) — bypasses admin-internal
 *     fields like `tenantId` even if a future group widens scope,
 *   - the per-profile attribute allow-list, exposed in the context
 *     under `api_profile_included_attributes` so a downstream
 *     normalizer can prune the response to only those attribute
 *     codes (live preview / external API).
 *
 * Decorates AP4's default builder; sits at high priority to run on
 * top of `KindAwareSerializerContextBuilder` from #41 and
 * `ContextScopeSerializerContextBuilder` from #42.
 */
final readonly class ApiProfileSerializerContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private SerializerContextBuilderInterface $decorated,
        private ApiProfileResolver $resolver,
    ) {
    }

    /**
     * @param array<string, mixed>|null $extractedAttributes
     *
     * @return array<string, mixed>
     */
    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        if (!$normalization) {
            return $context;
        }

        $profile = $this->resolver->resolveFromRequest($request);
        if (null === $profile) {
            return $context;
        }

        // Switch the read groups to the public-only set. The admin
        // group is dropped — fields like `tenantId` never reach a
        // partner integration regardless of caller scope.
        $context['groups'] = ['public:read'];

        // Hand off the attribute allow-list + filters so a downstream
        // normalizer / processor can prune the response without
        // re-querying the profile. Empty list = no projection (return
        // all read-group fields).
        if ([] !== $profile->getIncludedAttributes()) {
            $context['api_profile_included_attributes'] = $profile->getIncludedAttributes();
        }
        if ([] !== $profile->getObjectTypeIds()) {
            $context['api_profile_object_type_ids'] = $profile->getObjectTypeIds();
        }
        $context['api_profile_code'] = $profile->getCode();

        return $context;
    }
}
