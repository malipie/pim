<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http\Pagination;

use App\Integration\Generic\Domain\Enum\PaginationStrategyName;
use App\Integration\Generic\Infrastructure\Http\RecordSelector;

/**
 * Opaque-cursor paging — the first page carries no cursor; each response embeds
 * the next cursor at `cursorPath`, replayed as `?cursor=…` until it runs dry
 * (ADR-0022, epic APIC, ticket APIC-P2-03).
 */
final readonly class CursorPaginationStrategy implements PaginationStrategy
{
    public function __construct(private RecordSelector $selector)
    {
    }

    public function name(): PaginationStrategyName
    {
        return PaginationStrategyName::Cursor;
    }

    public function firstPage(PaginationConfig $config): PageRequest
    {
        return new PageRequest([$config->limitParam => $config->limit]);
    }

    public function nextPage(PaginationConfig $config, PageState $state): ?PageRequest
    {
        $cursor = $this->selector->value($state->decodedBody, $config->cursorPath);

        if (!\is_string($cursor) && !\is_int($cursor)) {
            return null;
        }

        $cursor = (string) $cursor;
        if ('' === $cursor) {
            return null;
        }

        return new PageRequest([$config->cursorParam => $cursor, $config->limitParam => $config->limit]);
    }
}
