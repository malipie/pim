<?php

declare(strict_types=1);

namespace App\Search\Application;

use App\Catalog\Domain\ObjectKind;
use App\Identity\Application\CurrentTenantProvider;
use App\Search\Infrastructure\MeilisearchClientFactory;
use App\Shared\Domain\Tenant;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Per-kind catalog search front-end (#52 / 0.5.4).
 *
 * Wraps Meilisearch's `search()` call with:
 *   - mandatory tenant scope (`filter=tenantId=<auth tenant>`) — hub
 *     answers across every tenant unless we constrain it here;
 *   - optional facet distribution (`?facets=brand,category,...`) so
 *     the admin sidebar can render counts;
 *   - optional highlighting (`?highlight=true`) wrapping match terms
 *     in `<em>` so the UI doesn't have to re-tokenise.
 *
 * Pagination is offset-based via Meili's `offset` + `limit` — search
 * lists are short-lived (UX rarely deep-pages search), so a cursor is
 * overkill. The list path under `/api/products` (#41) keeps the
 * cursor pagination for full catalog scans.
 */
final readonly class CatalogSearchService
{
    private LoggerInterface $logger;

    public function __construct(
        private MeilisearchClientFactory $clientFactory,
        private CurrentTenantProvider $tenantProvider,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param array<string, scalar|list<scalar>>             $filters                plain key→value / key→[v1,v2] OR matches on top of tenant scope
     * @param list<string>                                   $facets                 Attribute codes to facet on
     * @param array<string, mixed>                           $extra                  forward-compat — Meili options not surfaced here yet (sort, ranking)
     * @param array<string, array{gte?: float, lte?: float}> $rangeFilters           numeric range filters (UI-02.24); mapped to Meili `key >= N AND key <= N`
     * @param ?string                                        $customFilterExpression VIEW-10 — pre-built Meilisearch filter expression from FilterDslResolver::toMeilisearchFilter() AND-merged with tenant + flat filters
     *
     * @return array{hits: list<array<string, mixed>>, totalHits: int, facetDistribution: array<string, mixed>, processingTimeMs: int}
     */
    public function search(
        ObjectKind $kind,
        string $query,
        array $filters = [],
        array $facets = [],
        int $page = 1,
        int $perPage = 30,
        bool $highlight = false,
        array $extra = [],
        array $rangeFilters = [],
        ?string $customFilterExpression = null,
    ): array {
        if (ObjectKind::Custom === $kind) {
            return $this->emptyResult();
        }

        $tenant = $this->tenantProvider->getCurrent();
        if (!$tenant instanceof Tenant) {
            // No active tenant context — defence-in-depth: refuse to
            // hit the hub at all. Cross-tenant leak would break the
            // multi-tenant isolation contract.
            return $this->emptyResult();
        }

        $tenantFilter = \sprintf('tenantId = "%s"', $tenant->getId()->toRfc4122());
        $extraFilters = [];
        foreach ($filters as $key => $value) {
            if (\is_array($value)) {
                $orParts = [];
                foreach ($value as $v) {
                    $orParts[] = \sprintf('%s = "%s"', $key, addslashes((string) $v));
                }
                if ([] !== $orParts) {
                    $extraFilters[] = '('.implode(' OR ', $orParts).')';
                }
                continue;
            }
            $extraFilters[] = \sprintf('%s = "%s"', $key, addslashes((string) $value));
        }
        foreach ($rangeFilters as $key => $range) {
            if (isset($range['gte'])) {
                $extraFilters[] = \sprintf('%s >= %s', $key, $range['gte']);
            }
            if (isset($range['lte'])) {
                $extraFilters[] = \sprintf('%s <= %s', $key, $range['lte']);
            }
        }
        if (null !== $customFilterExpression && '' !== trim($customFilterExpression)) {
            $extraFilters[] = '('.$customFilterExpression.')';
        }
        $filterExpression = trim($tenantFilter.([] !== $extraFilters ? ' AND '.implode(' AND ', $extraFilters) : ''));

        $options = [
            'filter' => $filterExpression,
            'limit' => $perPage,
            'offset' => max(0, ($page - 1) * $perPage),
        ];
        if ([] !== $facets) {
            $options['facets'] = $facets;
        }
        if ($highlight) {
            $options['attributesToHighlight'] = ['*'];
            $options['highlightPreTag'] = '<em>';
            $options['highlightPostTag'] = '</em>';
        }
        $options = array_merge($options, $extra);

        try {
            $client = $this->clientFactory->create();
            $result = $client->index(IndexSettingsTemplate::indexName($kind))->search($query, $options);
            $raw = $result->toArray();
        } catch (Throwable $e) {
            $this->logger->warning('Meilisearch query failed: {message}', [
                'message' => $e->getMessage(),
                'kind' => $kind->value,
                'query' => $query,
            ]);

            return $this->emptyResult();
        }

        $rawHits = $raw['hits'] ?? [];
        $hits = [];
        if (\is_array($rawHits)) {
            foreach ($rawHits as $hit) {
                if (\is_array($hit)) {
                    $normalised = [];
                    foreach ($hit as $k => $v) {
                        $normalised[(string) $k] = $v;
                    }
                    $hits[] = $normalised;
                }
            }
        }

        $rawTotal = $raw['estimatedTotalHits'] ?? $raw['totalHits'] ?? 0;
        $rawProcessing = $raw['processingTimeMs'] ?? 0;
        $rawFacets = $raw['facetDistribution'] ?? null;
        $facets = [];
        if (\is_array($rawFacets)) {
            foreach ($rawFacets as $k => $v) {
                $facets[(string) $k] = $v;
            }
        }

        return [
            'hits' => $hits,
            'totalHits' => \is_numeric($rawTotal) ? (int) $rawTotal : 0,
            'facetDistribution' => $facets,
            'processingTimeMs' => \is_numeric($rawProcessing) ? (int) $rawProcessing : 0,
        ];
    }

    /**
     * @return array{hits: list<array<string, mixed>>, totalHits: int, facetDistribution: array<string, mixed>, processingTimeMs: int}
     */
    private function emptyResult(): array
    {
        return [
            'hits' => [],
            'totalHits' => 0,
            'facetDistribution' => [],
            'processingTimeMs' => 0,
        ];
    }
}
