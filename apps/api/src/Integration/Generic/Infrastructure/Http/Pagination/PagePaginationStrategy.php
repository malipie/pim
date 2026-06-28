<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http\Pagination;

use App\Integration\Generic\Domain\Enum\PaginationStrategyName;

/**
 * Page-number paging — `?page=1&limit=N`, `?page=2&limit=N`, … until a short
 * (or empty) page signals the end (ADR-0022, epic APIC, ticket APIC-P2-03).
 */
final class PagePaginationStrategy implements PaginationStrategy
{
    public function name(): PaginationStrategyName
    {
        return PaginationStrategyName::Page;
    }

    public function firstPage(PaginationConfig $config): PageRequest
    {
        return new PageRequest([$config->pageParam => $config->startPage, $config->limitParam => $config->limit]);
    }

    public function nextPage(PaginationConfig $config, PageState $state): ?PageRequest
    {
        if ($state->recordCount < $config->limit) {
            return null;
        }

        $nextPage = $config->startPage + $state->pageIndex + 1;

        return new PageRequest([$config->pageParam => $nextPage, $config->limitParam => $config->limit]);
    }
}
