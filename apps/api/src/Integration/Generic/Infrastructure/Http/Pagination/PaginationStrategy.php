<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http\Pagination;

use App\Integration\Generic\Domain\Enum\PaginationStrategyName;

/**
 * A driver that walks one external list endpoint (ADR-0022, epic APIC, ticket
 * APIC-P2-03).
 *
 * `firstPage()` yields the params for page 1; `nextPage()` inspects the page
 * just fetched and returns the next {@see PageRequest}, or null when the walk
 * is complete. Strategies are pure — the {@see PaginatedFetcher} owns the HTTP,
 * record extraction and the infinite-loop guard.
 */
interface PaginationStrategy
{
    public function name(): PaginationStrategyName;

    public function firstPage(PaginationConfig $config): PageRequest;

    public function nextPage(PaginationConfig $config, PageState $state): ?PageRequest;
}
