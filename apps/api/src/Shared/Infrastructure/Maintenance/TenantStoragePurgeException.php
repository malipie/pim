<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Maintenance;

use RuntimeException;

/**
 * AUD-020 (W1-7) — raised when a tenant's database rows were deleted but
 * one or more object-storage prefixes could not be removed. Signals the
 * offboarding caller that the purge is incomplete: PII blobs may still
 * exist under `<tenant-uuid>/` and require a manual / GC sweep.
 */
final class TenantStoragePurgeException extends RuntimeException
{
    /**
     * @param list<string> $failedBuckets
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly array $failedBuckets,
    ) {
        parent::__construct(\sprintf(
            'Tenant %s storage purge failed for bucket(s): %s. DB rows are deleted; blobs may linger.',
            $tenantId,
            implode(', ', $failedBuckets),
        ));
    }
}
