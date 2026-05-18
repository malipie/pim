<?php

declare(strict_types=1);

namespace App\Identity\Domain\Attribute;

use Attribute;

/**
 * Explicitly marks a controller action as not requiring an RBAC
 * permission check. Static analysis (RequiresPermissionAnnotationRule)
 * requires every `#[Route]` method to carry either `#[RequiresPermission]`
 * or `#[NoPermissionRequired]` so an unannotated endpoint never silently
 * ships without authorisation gating.
 *
 * Legitimate uses:
 *   - Public auth flows: `/login`, `/password-reset`, magic-link accept,
 *     `/api/me` (the authenticated user is implicit subject).
 *   - Health-check / readiness probes.
 *   - Webhook receivers authenticated by HMAC signature rather than RBAC.
 *
 * Always pair with a `reason:` argument so the next reviewer understands
 * why the endpoint sits outside the permission graph.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class NoPermissionRequired
{
    public function __construct(
        public readonly string $reason,
    ) {
    }
}
