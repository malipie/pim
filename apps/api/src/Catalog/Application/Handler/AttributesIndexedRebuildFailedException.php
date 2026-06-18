<?php

declare(strict_types=1);

namespace App\Catalog\Application\Handler;

use RuntimeException;

/**
 * AUD-039 / G-01 — raised by {@see RebuildAttributesIndexedHandler} when one
 * or more objects could not have their `attributes_indexed` cache rebuilt
 * after exhausting the per-id version-conflict retries.
 *
 * Before this, the handler logged a Warning and returned normally, so the
 * Messenger envelope completed "successfully" and the drift was invisible:
 * no `failed` transport entry, no retry. Throwing instead lets the async
 * transport's retry policy re-deliver the batch (the rebuild is idempotent,
 * so already-rebuilt ids are cheap no-ops) and, once those are exhausted,
 * dead-letters the envelope to the `failed` transport where
 * {@see \App\Catalog\Infrastructure\Messenger\AttributesIndexedRebuildDeadLetterListener}
 * logs it at error level. Drift becomes loud, never silent.
 */
final class AttributesIndexedRebuildFailedException extends RuntimeException
{
    /**
     * @param list<string> $objectIds the ids that could not be rebuilt
     */
    public function __construct(public readonly array $objectIds)
    {
        parent::__construct(\sprintf(
            'attributes_indexed rebuild failed for %d object(s) after exhausting version-conflict retries: %s',
            \count($objectIds),
            implode(', ', $objectIds),
        ));
    }
}
