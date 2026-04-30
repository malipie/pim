<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Carries the originating tenant id across the messenger envelope so
 * the worker-side middleware can rebind the tenant context before the
 * handler runs.
 *
 * Domain events that already expose a `tenantId` field (e.g. Catalog's
 * `ObjectCreated`) work without a stamp — the middleware reads either
 * source. The stamp exists for synthetic messages that have no natural
 * tenant field but still need worker isolation (e.g. cron-dispatched
 * sync jobs in Faza 1).
 */
final readonly class TenantStamp implements StampInterface
{
    public function __construct(
        public Uuid $tenantId,
    ) {
    }
}
