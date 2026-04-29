<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform;

use ApiPlatform\State\SerializerContextBuilderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decorator that selects the serialization scope (`admin` / `integration`
 * / `public`) per request (#42 / 0.4.2).
 *
 * MVP wiring: the explicit `?context=integration|public` query override
 * lets clients (and tests) opt into reduced views without waiting for
 * API-key-driven context selection that lands with the API Configurator
 * in epic 0.10 (#94). Authenticated requests without the override keep
 * the resource-default `admin:read` group set.
 *
 * The decorator runs **before** {@see KindAwareSerializerContextBuilder}
 * (which is opt-in: it appends per-kind groups when groups are already
 * present). When a query override is in play, the chain becomes
 * `[scope:read]` → `[scope:read, kind:scope:read]` for the matching
 * sugar path. No override = the resource's declared `admin:read`
 * persists, KindAwareBuilder layers the kind on top.
 *
 * Sensitive fields (`tenant`, `completeness`) are kept out of the
 * `integration:read` and `public:read` group sets in the Symfony
 * Serializer XML metadata files (`Catalog/Infrastructure/Serializer/`),
 * so even a malicious `?context=public` cannot leak them.
 */
final readonly class ContextScopeSerializerContextBuilder implements SerializerContextBuilderInterface
{
    private const string QUERY_PARAMETER = 'context';

    /**
     * @var array<string, list<string>>
     */
    private const array SCOPE_GROUPS = [
        'admin' => ['admin:read'],
        'integration' => ['integration:read'],
        'public' => ['public:read'],
    ];

    public function __construct(
        private SerializerContextBuilderInterface $decorated,
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        // Only inbound (read) responses route through the scope override —
        // write operations always run their declared `denormalizationContext`
        // (e.g. `object:create` / `object:patch`).
        if (!$normalization) {
            return $context;
        }

        $scopeValue = $request->query->get(self::QUERY_PARAMETER);
        if (!\is_string($scopeValue)) {
            return $context;
        }

        $scope = self::SCOPE_GROUPS[$scopeValue] ?? null;
        if (null === $scope) {
            return $context;
        }

        $context['groups'] = $scope;

        return $context;
    }
}
