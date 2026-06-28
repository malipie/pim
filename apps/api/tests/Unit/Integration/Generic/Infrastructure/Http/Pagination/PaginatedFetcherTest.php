<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Infrastructure\Http\Pagination;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Enum\RemoteEndpointRole;
use App\Integration\Generic\Domain\GenericRestResponse;
use App\Integration\Generic\Infrastructure\Http\Pagination\CursorPaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\LinkHeaderPaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\NonePaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\OffsetPaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\PagePaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\PaginatedFetcher;
use App\Integration\Generic\Infrastructure\Http\Pagination\PaginationStrategies;
use App\Integration\Generic\Infrastructure\Http\RecordSelector;
use App\Integration\Generic\Infrastructure\Http\RemoteRequester;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaginatedFetcherTest extends TestCase
{
    #[Test]
    public function offsetWalkAggregatesEveryPageUntilAShortOne(): void
    {
        $requester = $this->requester([
            $this->jsonPage([['sku' => 'A'], ['sku' => 'B']]),
            $this->jsonPage([['sku' => 'C'], ['sku' => 'D']]),
            $this->jsonPage([['sku' => 'E']]),
        ]);
        $fetcher = $this->fetcher($requester);
        $endpoint = $this->endpoint(['strategy' => 'offset', 'limit' => 2], '$');

        $records = iterator_to_array($fetcher->records($this->connection(), $endpoint), false);

        self::assertCount(5, $records);
        self::assertSame(['A', 'B', 'C', 'D', 'E'], array_column($records, 'sku'));
        // Three requests with advancing offsets, built off baseUrl + pathTemplate.
        self::assertCount(3, $requester->calls);
        self::assertSame('https://api.example.com/v1/products', $requester->calls[0]['url']);
        self::assertSame(['offset' => 0, 'limit' => 2], $requester->calls[0]['query']);
        self::assertSame(['offset' => 2, 'limit' => 2], $requester->calls[1]['query']);
        self::assertSame(['offset' => 4, 'limit' => 2], $requester->calls[2]['query']);
    }

    #[Test]
    public function recordSelectorExtractsFromAnEnvelope(): void
    {
        $requester = $this->requester([
            new GenericRestResponse(200, [], (string) json_encode(['results' => [['id' => 1], ['id' => 2]]]), 1, 2),
        ]);
        $fetcher = $this->fetcher($requester);
        $endpoint = $this->endpoint(['strategy' => 'none'], '$.results');

        $records = iterator_to_array($fetcher->records($this->connection(), $endpoint), false);

        self::assertSame([1, 2], array_column($records, 'id'));
    }

    #[Test]
    public function staticQueryParamsRideAlongWithPagination(): void
    {
        $requester = $this->requester([$this->jsonPage([['id' => 1]])]);
        $fetcher = $this->fetcher($requester);
        $endpoint = $this->endpoint(['strategy' => 'offset', 'limit' => 50], '$');
        $endpoint->setQueryParams(['locale' => 'pl']);

        iterator_to_array($fetcher->records($this->connection(), $endpoint), false);

        self::assertSame(['locale' => 'pl', 'offset' => 0, 'limit' => 50], $requester->calls[0]['query']);
    }

    #[Test]
    public function aNonSuccessfulPageEndsTheWalk(): void
    {
        $requester = $this->requester([
            $this->jsonPage([['id' => 1], ['id' => 2]]),
            new GenericRestResponse(500, [], 'oops', 1, 4),
        ]);
        $fetcher = $this->fetcher($requester);
        $endpoint = $this->endpoint(['strategy' => 'offset', 'limit' => 2], '$');

        $records = iterator_to_array($fetcher->records($this->connection(), $endpoint), false);

        // First page kept; the 500 stops the walk without yielding more.
        self::assertCount(2, $records);
        self::assertCount(2, $requester->calls);
    }

    #[Test]
    public function loopGuardStopsAnEndlesslyAdvancingWalk(): void
    {
        // Every page is full → the offset strategy never short-circuits.
        $requester = new RecordingRequester(default: new GenericRestResponse(200, [], '[{"id":1},{"id":2}]', 1, 8));
        $fetcher = $this->fetcher($requester);
        $endpoint = $this->endpoint(['strategy' => 'offset', 'limit' => 2], '$');

        $pages = 0;
        foreach ($fetcher->pages($this->connection(), $endpoint) as $_page) {
            ++$pages;
        }

        self::assertSame(PaginatedFetcher::MAX_PAGES, $pages);
        self::assertCount(PaginatedFetcher::MAX_PAGES, $requester->calls);
    }

    /**
     * @param list<GenericRestResponse> $responses
     */
    private function requester(array $responses): RecordingRequester
    {
        return new RecordingRequester($responses);
    }

    private function fetcher(RemoteRequester $requester): PaginatedFetcher
    {
        $strategies = new PaginationStrategies([
            new NonePaginationStrategy(),
            new OffsetPaginationStrategy(),
            new PagePaginationStrategy(),
            new CursorPaginationStrategy(new RecordSelector()),
            new LinkHeaderPaginationStrategy(),
        ]);

        return new PaginatedFetcher($requester, new RecordSelector(), $strategies);
    }

    private function connection(): Connection
    {
        return new Connection('idosell', 'IdoSell', 'https://api.example.com/v1');
    }

    /**
     * @param array<string, mixed> $pagination
     */
    private function endpoint(array $pagination, string $selector): RemoteEndpoint
    {
        $endpoint = new RemoteEndpoint($this->connection(), RemoteEndpointRole::ReadList, 'GET', '/products');
        $endpoint->setPagination($pagination);
        $endpoint->setRecordSelector($selector);

        return $endpoint;
    }

    /**
     * @param list<array<string, mixed>> $records
     */
    private function jsonPage(array $records): GenericRestResponse
    {
        return new GenericRestResponse(200, [], (string) json_encode($records), 1, 16);
    }
}
