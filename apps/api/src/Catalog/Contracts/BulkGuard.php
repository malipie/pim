<?php

declare(strict_types=1);

namespace App\Catalog\Contracts;

/**
 * Contracts-level read view of {@see \App\Catalog\Application\BulkContext} so
 * cross-context subscribers (e.g. ApiConfigurator webhooks) can opt out of
 * per-object work during a bulk run without depending on Catalog\Application
 * internals (Deptrac layering).
 */
interface BulkGuard
{
    public function isBulk(): bool;
}
