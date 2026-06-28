<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http\Pagination;

use App\Integration\Generic\Domain\Enum\PaginationStrategyName;

/**
 * No paging — fetch the single page and stop (ADR-0022, epic APIC).
 */
final class NonePaginationStrategy implements PaginationStrategy
{
    public function name(): PaginationStrategyName
    {
        return PaginationStrategyName::None;
    }

    public function firstPage(PaginationConfig $config): PageRequest
    {
        return new PageRequest();
    }

    public function nextPage(PaginationConfig $config, PageState $state): ?PageRequest
    {
        return null;
    }
}
