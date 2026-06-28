<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Discovery;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Infrastructure\Http\Pagination\PaginatedFetcher;

/**
 * Proposes a schema for a read endpoint by sampling its first page (ADR-0022,
 * epic APIC, ticket APIC-P2-04).
 *
 * It pulls one page through the SSRF-safe {@see PaginatedFetcher} (so a giant
 * endpoint is never fully walked just to discover its shape), takes the first
 * record, and flattens it into typed {@see DiscoveredField} proposals. The
 * wizard (APIC-P2-06) lets the user accept/edit before the CRUD endpoint
 * (APIC-P2-05) persists them. Only public list responses are sampled — no
 * credentials ever flow into the result or logs.
 */
final readonly class SchemaDiscoveryService
{
    public function __construct(
        private PaginatedFetcher $fetcher,
        private JsonFlattener $flattener,
    ) {
    }

    public function discover(Connection $connection, RemoteEndpoint $endpoint): SchemaDiscoveryResult
    {
        $page = $this->firstPage($connection, $endpoint);
        $sample = $page[0] ?? null;

        if (!\is_array($sample) || [] === $sample) {
            return SchemaDiscoveryResult::empty();
        }

        return new SchemaDiscoveryResult(
            $this->flattener->flatten($sample),
            $sample,
            \count($page),
        );
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function firstPage(Connection $connection, RemoteEndpoint $endpoint): array
    {
        foreach ($this->fetcher->pages($connection, $endpoint) as $page) {
            return $page;
        }

        return [];
    }
}
