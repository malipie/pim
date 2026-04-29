<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform;

use App\Catalog\Domain\Exception\DisabledFeatureException;
use App\Catalog\Domain\ObjectKind;

/**
 * API-layer counterpart to the service-layer feature flag enforcement in
 * {@see \App\Catalog\Application\ObjectTypeService}.
 *
 * The DB CHECK constraint allows `kind='custom'` rows to exist (mitigation
 * R-29 — schema is forward-compatible with phase 2/3 custom kinds), and
 * the service layer guard rejects programmatic creation. This guard is
 * the third layer: an explicit assertion the API denormalizers in #41
 * call when accepting an inbound `ObjectType` payload, so a future
 * regression in the service layer does not silently leak `custom` rows
 * out via REST.
 *
 * Defensive triple-layering is intentional — the cost is one constructor
 * + one `assertAllowed` call per write request.
 */
final readonly class CustomObjectTypeApiGuard
{
    public function __construct(
        private bool $enableCustomObjectTypes,
    ) {
    }

    /**
     * Throws when the kind is `Custom` and the feature flag is off.
     * Safe to call for every kind — built-in kinds short-circuit.
     */
    public function assertAllowed(ObjectKind $kind): void
    {
        if (ObjectKind::Custom !== $kind) {
            return;
        }
        if (!$this->enableCustomObjectTypes) {
            throw DisabledFeatureException::customObjectTypesDisabled();
        }
    }

    public function isCustomKindEnabled(): bool
    {
        return $this->enableCustomObjectTypes;
    }
}
