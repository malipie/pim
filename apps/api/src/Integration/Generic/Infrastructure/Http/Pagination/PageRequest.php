<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http\Pagination;

/**
 * The next page to fetch as computed by a {@see PaginationStrategy} (ADR-0022,
 * epic APIC, ticket APIC-P2-03).
 *
 * `query` carries the pagination params merged onto the endpoint's static query
 * by the fetcher. `url`, when set (link_header), overrides the endpoint's base
 * URL with the absolute next link the remote returned — it still passes the
 * SSRF guard on the next request.
 *
 * @phpstan-type QueryParams array<string, string|int>
 */
final readonly class PageRequest
{
    /**
     * @param array<string, string|int> $query
     */
    public function __construct(
        public array $query = [],
        public ?string $url = null,
    ) {
    }
}
