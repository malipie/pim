<?php

declare(strict_types=1);

namespace App\Catalog\Application\Message;

/**
 * Asynchronous trigger to rebuild `attributes_indexed` + `completeness`
 * for a batch of `CatalogObject` rows that had their values changed by
 * a bulk path (CSV import, agent operation, etc).
 *
 * The bulk handler dispatches one message per chunk it processes,
 * carrying the affected object ids; {@see \App\Catalog\Application\Handler\RebuildAttributesIndexedHandler}
 * picks them up and rebuilds on a worker process so the request
 * thread can return immediately.
 *
 * @see \App\Catalog\Application\BulkContext
 */
final readonly class ObjectValuesChangedMessage
{
    /**
     * @param list<string> $objectIds RFC-4122 UUID strings
     */
    public function __construct(
        public array $objectIds,
    ) {
    }
}
