<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http\Pagination;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Infrastructure\Http\RecordSelector;
use App\Integration\Generic\Infrastructure\Http\RemoteRequester;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Walks a read endpoint page by page through the SSRF-safe requester, applying
 * its descriptor's pagination strategy and record selector (ADR-0022, epic
 * APIC, ticket APIC-P2-03).
 *
 * `pages()` is a generator, so a 50k-record pull never materialises in memory —
 * the sync handler (APIC-P3-04) consumes one page at a time and clears the
 * Doctrine unit of work between batches (FrankenPHP worker hygiene). A hard
 * page cap guards against a misconfigured cursor/offset that never terminates;
 * a non-2xx page ends the walk (the caller decides what a partial pull means).
 */
final readonly class PaginatedFetcher
{
    /** Infinite-loop guard: stop after this many pages regardless of strategy. */
    public const int MAX_PAGES = 10_000;

    public function __construct(
        private RemoteRequester $requester,
        private RecordSelector $recordSelector,
        private PaginationStrategies $strategies,
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * Yields one batch of records per page, in order.
     *
     * @return iterable<int, list<array<array-key, mixed>>>
     */
    public function pages(Connection $connection, RemoteEndpoint $endpoint): iterable
    {
        $config = PaginationConfig::fromArray($endpoint->getPagination());
        $strategy = $this->strategies->get($config->strategy);
        $baseUrl = $this->buildUrl($connection, $endpoint);
        $request = $strategy->firstPage($config);

        for ($pageIndex = 0; null !== $request; ++$pageIndex) {
            if ($pageIndex >= self::MAX_PAGES) {
                $this->logger->warning('Pagination page cap reached; stopping walk.', [
                    'connection' => $connection->getCode(),
                    'endpoint' => $endpoint->getId()->toRfc4122(),
                    'cap' => self::MAX_PAGES,
                ]);
                break;
            }

            $url = $request->url ?? $baseUrl;
            $query = array_merge($endpoint->getQueryParams(), $request->query);

            $response = $this->requester->request($connection, $endpoint->getHttpMethod(), $url, $query);
            if (!$response->isSuccessful()) {
                $this->logger->warning('External list page returned non-2xx; stopping walk.', [
                    'connection' => $connection->getCode(),
                    'endpoint' => $endpoint->getId()->toRfc4122(),
                    'status' => $response->statusCode,
                    'page' => $pageIndex,
                ]);
                break;
            }

            $decoded = json_decode($response->body, true);
            $records = $this->recordSelector->records($decoded, $endpoint->getRecordSelector());

            yield $records;

            $request = $strategy->nextPage(
                $config,
                new PageState($pageIndex, \count($records), $response, $decoded),
            );
        }
    }

    /**
     * Flattens {@see pages()} into a single record stream.
     *
     * @return iterable<int, array<array-key, mixed>>
     */
    public function records(Connection $connection, RemoteEndpoint $endpoint): iterable
    {
        foreach ($this->pages($connection, $endpoint) as $page) {
            yield from $page;
        }
    }

    private function buildUrl(Connection $connection, RemoteEndpoint $endpoint): string
    {
        $path = ltrim($endpoint->getPathTemplate(), '/');
        if ('' === $path) {
            return $connection->getBaseUrl();
        }

        return rtrim($connection->getBaseUrl(), '/').'/'.$path;
    }
}
