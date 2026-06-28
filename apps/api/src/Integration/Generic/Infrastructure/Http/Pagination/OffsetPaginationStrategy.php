<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http\Pagination;

use App\Integration\Generic\Domain\Enum\PaginationStrategyName;

/**
 * Offset/limit paging — `?offset=0&limit=N`, `?offset=N&limit=N`, … until a
 * short (or empty) page signals the end (ADR-0022, epic APIC, ticket APIC-P2-03).
 */
final class OffsetPaginationStrategy implements PaginationStrategy
{
    public function name(): PaginationStrategyName
    {
        return PaginationStrategyName::Offset;
    }

    public function firstPage(PaginationConfig $config): PageRequest
    {
        return new PageRequest([$config->offsetParam => 0, $config->limitParam => $config->limit]);
    }

    public function nextPage(PaginationConfig $config, PageState $state): ?PageRequest
    {
        // A page shorter than the limit (or empty) is the last one.
        if ($state->recordCount < $config->limit) {
            return null;
        }

        $nextOffset = ($state->pageIndex + 1) * $config->limit;

        return new PageRequest([$config->offsetParam => $nextOffset, $config->limitParam => $config->limit]);
    }
}
