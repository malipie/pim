<?php

declare(strict_types=1);

namespace App\Shared\Application;

use App\Shared\Domain\Tenant;
use RuntimeException;

/**
 * PROD-05 — thrown by code paths gated by {@see BulkOperationLock} when
 * the tenant already has an in-flight bulk job. Callers translate this
 * into the right surface: the synchronous controller path returns HTTP
 * 409, the async Messenger handler re-throws as
 * `RecoverableMessageHandlingException` so the queue retries with the
 * configured backoff.
 */
final class BulkOperationInProgressException extends RuntimeException
{
    public function __construct(public readonly Tenant $tenant)
    {
        parent::__construct(\sprintf(
            'Tenant "%s" already has a bulk operation in progress.',
            $tenant->getId()->toRfc4122(),
        ));
    }
}
