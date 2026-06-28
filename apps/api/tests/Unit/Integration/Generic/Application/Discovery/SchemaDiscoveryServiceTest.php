<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application\Discovery;

use App\Integration\Generic\Application\Discovery\JsonFlattener;
use App\Integration\Generic\Application\Discovery\SchemaDiscoveryService;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Enum\RemoteEndpointRole;
use App\Integration\Generic\Domain\Enum\RemoteFieldDataType;
use App\Integration\Generic\Domain\GenericRestResponse;
use App\Integration\Generic\Infrastructure\Http\Pagination\CursorPaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\LinkHeaderPaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\NonePaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\OffsetPaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\PagePaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\PaginatedFetcher;
use App\Integration\Generic\Infrastructure\Http\Pagination\PaginationStrategies;
use App\Integration\Generic\Infrastructure\Http\RecordSelector;
use App\Tests\Unit\Integration\Generic\Infrastructure\Http\Pagination\RecordingRequester;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaDiscoveryServiceTest extends TestCase
{
    #[Test]
    public function proposesTypedFieldsFromTheFirstSampleRecord(): void
    {
        $body = json_encode([
            'results' => [
                ['sku' => 'A-1', 'price' => ['amount' => 1999], 'tags' => ['new']],
                ['sku' => 'B-2', 'price' => ['amount' => 2999], 'tags' => []],
            ],
        ]);
        $service = $this->service([new GenericRestResponse(200, [], (string) $body, 1, 64)]);

        $result = $service->discover($this->connection(), $this->endpoint('$.results'));

        $byPath = [];
        foreach ($result->fields as $field) {
            $byPath[$field->path] = $field;
        }

        self::assertSame(RemoteFieldDataType::String, $byPath['$.sku']->dataType);
        self::assertSame(RemoteFieldDataType::Integer, $byPath['$.price.amount']->dataType);
        self::assertSame(RemoteFieldDataType::Array, $byPath['$.tags']->dataType);
        // Sample + count come from the first page.
        self::assertSame('A-1', $result->sampleRecord['sku']);
        self::assertSame(2, $result->sampledRecords);
    }

    #[Test]
    public function returnsEmptyWhenTheSampleHasNoRecords(): void
    {
        $service = $this->service([new GenericRestResponse(200, [], '{"results":[]}', 1, 16)]);

        $result = $service->discover($this->connection(), $this->endpoint('$.results'));

        self::assertSame([], $result->fields);
        self::assertSame([], $result->sampleRecord);
        self::assertSame(0, $result->sampledRecords);
    }

    /**
     * @param list<GenericRestResponse> $responses
     */
    private function service(array $responses): SchemaDiscoveryService
    {
        $strategies = new PaginationStrategies([
            new NonePaginationStrategy(),
            new OffsetPaginationStrategy(),
            new PagePaginationStrategy(),
            new CursorPaginationStrategy(new RecordSelector()),
            new LinkHeaderPaginationStrategy(),
        ]);
        $fetcher = new PaginatedFetcher(new RecordingRequester($responses), new RecordSelector(), $strategies);

        return new SchemaDiscoveryService($fetcher, new JsonFlattener());
    }

    private function connection(): Connection
    {
        return new Connection('idosell', 'IdoSell', 'https://api.example.com/v1');
    }

    private function endpoint(string $selector): RemoteEndpoint
    {
        $endpoint = new RemoteEndpoint($this->connection(), RemoteEndpointRole::ReadList, 'GET', '/products');
        $endpoint->setPagination(['strategy' => 'none']);
        $endpoint->setRecordSelector($selector);

        return $endpoint;
    }
}
