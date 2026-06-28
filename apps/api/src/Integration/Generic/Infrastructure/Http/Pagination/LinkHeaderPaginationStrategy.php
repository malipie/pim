<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http\Pagination;

use App\Integration\Generic\Domain\Enum\PaginationStrategyName;

/**
 * RFC 8288 `Link` header paging — follow the `rel="next"` URL until the remote
 * stops emitting one (GitHub/Shopify style) (ADR-0022, epic APIC, ticket
 * APIC-P2-03).
 */
final class LinkHeaderPaginationStrategy implements PaginationStrategy
{
    public function name(): PaginationStrategyName
    {
        return PaginationStrategyName::LinkHeader;
    }

    public function firstPage(PaginationConfig $config): PageRequest
    {
        // The base URL drives the first call; the limit is a hint if the API honours it.
        return new PageRequest([$config->limitParam => $config->limit]);
    }

    public function nextPage(PaginationConfig $config, PageState $state): ?PageRequest
    {
        $linkHeaders = $state->response->headers['link'] ?? [];
        $next = self::findRel($linkHeaders, $config->linkRel);

        return null === $next ? null : new PageRequest([], $next);
    }

    /**
     * Parses `<url>; rel="next", <url>; rel="prev"` across one or more Link
     * header values and returns the URL for the requested rel.
     *
     * @param list<string> $headers
     */
    private static function findRel(array $headers, string $rel): ?string
    {
        foreach ($headers as $header) {
            foreach (explode(',', $header) as $part) {
                if (1 !== preg_match('/<([^>]+)>\s*;\s*rel\s*=\s*"?([^";]+)"?/', $part, $matches)) {
                    continue;
                }
                if (strtolower(trim($matches[2])) === strtolower($rel)) {
                    return trim($matches[1]);
                }
            }
        }

        return null;
    }
}
