<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform;

use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\State\SerializerContextBuilderInterface;
use App\Catalog\Domain\ObjectKind;
use Symfony\Component\HttpFoundation\Request;

/**
 * Decorator on AP4's default serializer context builder that enriches the
 * context with the current `ObjectKind` when the matched operation has
 * `extraProperties.kind` set (the convention for the per-kind sugar paths
 * landing in #41).
 *
 * Today the decorator is a near no-op — there are no `#[ApiResource]`
 * declarations on `CatalogObject` yet (#41 ticket of epic 0.4 wires them
 * in). Shipping the decorator now means #41 can drop the kind-aware
 * normalizers + voters in without re-touching the bootstrap chain.
 *
 * Built-in kinds get their per-kind serializer groups merged in via
 * {@see ObjectKindRouter::groupsFor}. Custom kinds (phase 2/3) get only
 * the shared `object:read` group.
 */
final readonly class KindAwareSerializerContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private SerializerContextBuilderInterface $decorated,
        private ObjectKindRouter $router,
    ) {
    }

    public function createFromRequest(Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        $operation = $context['operation'] ?? null;
        if (!$operation instanceof HttpOperation) {
            return $context;
        }

        $kindValue = $operation->getExtraProperties()['kind'] ?? null;
        if (!\is_string($kindValue)) {
            return $context;
        }

        $kind = ObjectKind::tryFrom($kindValue);
        if (null === $kind) {
            return $context;
        }

        // Always expose `kind` to downstream normalisers — they may use it
        // for per-kind branching (e.g. attribute denormalisation in #45).
        $context['kind'] = $kind->value;

        // Group filtering is opt-in: we only enrich the `groups` context
        // when the operation already declared one. Domain entities carry
        // no `#[Groups]` in #41 (the per-context group sets land with
        // dedicated output DTOs in #42), so unconditionally injecting
        // `object:read` here would filter every property out and yield
        // an empty JSON-LD body.
        $existing = $context['groups'] ?? null;
        if (null === $existing || [] === $existing) {
            return $context;
        }
        if (\is_string($existing)) {
            $existing = [$existing];
        }
        if (!\is_array($existing)) {
            return $context;
        }

        /** @var list<string> $existingStrings */
        $existingStrings = array_values(array_filter($existing, 'is_string'));

        $context['groups'] = array_values(array_unique([...$existingStrings, ...$this->router->groupsFor($kind)]));

        return $context;
    }
}
