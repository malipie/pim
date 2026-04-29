<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\GetObjectSummary;

use Symfony\Component\Uid\Uuid;

/**
 * Cross-BC entry point for fetching a single {@see \App\Catalog\Contracts\Query\ObjectSummary}.
 * Other BCs (Channel, Asset) pass a Uuid; the handler resolves the row
 * and returns a final-readonly DTO so callers cannot mutate Catalog
 * state through the read path.
 */
final readonly class GetObjectSummaryQuery
{
    public function __construct(public Uuid $objectId)
    {
    }
}
