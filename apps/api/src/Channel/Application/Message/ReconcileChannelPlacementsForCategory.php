<?php

declare(strict_types=1);

namespace App\Channel\Application\Message;

use App\Shared\Application\TenantAwareMessage;
use Symfony\Component\Uid\Uuid;

/**
 * #1314 — back-fill: after a channel node-mapping changes (CHC-08 save/delete),
 * reconcile the channel placements of every product assigned to the affected
 * master category. Dispatched async so the mapping request stays fast and a
 * category with many products is processed off-thread.
 */
final readonly class ReconcileChannelPlacementsForCategory implements TenantAwareMessage
{
    public function __construct(
        public string $masterCategoryId,
        public string $tenantId,
    ) {
    }

    public function tenantId(): Uuid
    {
        return Uuid::fromString($this->tenantId);
    }
}
