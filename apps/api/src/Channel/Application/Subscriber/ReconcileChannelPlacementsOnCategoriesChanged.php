<?php

declare(strict_types=1);

namespace App\Channel\Application\Subscriber;

use App\Catalog\Contracts\Event\ObjectCategoriesChanged;
use App\Channel\Application\Service\ReconcileObjectChannelPlacements;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * #1314 — on {@see ObjectCategoriesChanged}: reconcile the product's channel
 * placements from the node mappings of all its categories (primary precedence,
 * manual-wins, stale-auto cleanup). Replaces the primary-only CHC-07 handler.
 *
 * Async transport ({@see ObjectCategoriesChanged} is {@see \App\Shared\Application\TenantAwareMessage}
 * so the worker rebinds the tenant). Under the sync transport (dev/test) it runs
 * inline; the reconciler never clears the EM, so it is safe in post-flush.
 */
#[AsMessageHandler]
final readonly class ReconcileChannelPlacementsOnCategoriesChanged
{
    public function __construct(
        private ReconcileObjectChannelPlacements $reconciler,
    ) {
    }

    public function __invoke(ObjectCategoriesChanged $event): void
    {
        $this->reconciler->reconcile($event->objectId, $event->tenantId);
    }
}
