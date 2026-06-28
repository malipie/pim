<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http\Pagination;

use App\Integration\Generic\Domain\GenericRestResponse;

/**
 * The outcome of the page just fetched, handed to {@see PaginationStrategy::nextPage()}
 * to decide whether (and how) to advance (ADR-0022, epic APIC, ticket APIC-P2-03).
 */
final readonly class PageState
{
    /**
     * @param int   $pageIndex   zero-based index of the page just fetched
     * @param int   $recordCount records the selector extracted from it
     * @param mixed $decodedBody the json_decode(..., true) body (for cursor lookups)
     */
    public function __construct(
        public int $pageIndex,
        public int $recordCount,
        public GenericRestResponse $response,
        public mixed $decodedBody,
    ) {
    }
}
