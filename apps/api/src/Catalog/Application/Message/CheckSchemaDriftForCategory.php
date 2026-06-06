<?php

declare(strict_types=1);

namespace App\Catalog\Application\Message;

use App\Shared\Application\TenantAwareMessage;
use Symfony\Component\Uid\Uuid;

/**
 * CHC-05 (#1287) — dispatched after a confirmed category move that affects
 * products. The asynchronous CHC-04 handler ({@see \App\Catalog\Application\Handler\CheckSchemaDriftHandler})
 * re-evaluates each affected product's effective schema against its stored
 * snapshot and flags drift.
 *
 * Routed `async`. Carries category + tenant ids as RFC-4122 strings (no Uuid
 * objects across the transport boundary). Implements {@see TenantAwareMessage}
 * so the worker rebinds TenantContext on consume (there is no dispatch-side
 * TenantStamp writer in this codebase).
 */
final readonly class CheckSchemaDriftForCategory implements TenantAwareMessage
{
    public function __construct(
        public string $categoryId,
        public string $tenantId,
    ) {
    }

    public function tenantId(): Uuid
    {
        return Uuid::fromString($this->tenantId);
    }
}
