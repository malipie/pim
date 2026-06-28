<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Infrastructure\Http\Pagination;

use App\Integration\Generic\Domain\Enum\PaginationStrategyName;
use App\Integration\Generic\Domain\GenericRestResponse;
use App\Integration\Generic\Infrastructure\Http\Pagination\CursorPaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\LinkHeaderPaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\NonePaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\OffsetPaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\PagePaginationStrategy;
use App\Integration\Generic\Infrastructure\Http\Pagination\PageState;
use App\Integration\Generic\Infrastructure\Http\Pagination\PaginationConfig;
use App\Integration\Generic\Infrastructure\Http\RecordSelector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PaginationStrategyTest extends TestCase
{
    #[Test]
    public function noneFetchesASinglePage(): void
    {
        $strategy = new NonePaginationStrategy();
        $config = PaginationConfig::fromArray(['strategy' => 'none']);

        self::assertSame(PaginationStrategyName::None, $strategy->name());
        self::assertSame([], $strategy->firstPage($config)->query);
        self::assertNull($strategy->nextPage($config, $this->state(0, 50)));
    }

    #[Test]
    public function offsetAdvancesUntilAShortPage(): void
    {
        $strategy = new OffsetPaginationStrategy();
        $config = PaginationConfig::fromArray(['strategy' => 'offset', 'limit' => 100]);

        self::assertSame(['offset' => 0, 'limit' => 100], $strategy->firstPage($config)->query);
        // Full page → next offset = (0+1)*100.
        self::assertSame(['offset' => 100, 'limit' => 100], $strategy->nextPage($config, $this->state(0, 100))?->query);
        // Second full page → next offset = (1+1)*100.
        self::assertSame(['offset' => 200, 'limit' => 100], $strategy->nextPage($config, $this->state(1, 100))?->query);
        // Short page ends the walk.
        self::assertNull($strategy->nextPage($config, $this->state(2, 40)));
    }

    #[Test]
    public function offsetRespectsCustomParamNames(): void
    {
        $strategy = new OffsetPaginationStrategy();
        $config = PaginationConfig::fromArray([
            'strategy' => 'offset',
            'limit' => 25,
            'offsetParam' => 'skip',
            'limitParam' => 'take',
        ]);

        self::assertSame(['skip' => 0, 'take' => 25], $strategy->firstPage($config)->query);
        self::assertSame(['skip' => 25, 'take' => 25], $strategy->nextPage($config, $this->state(0, 25))?->query);
    }

    #[Test]
    public function pageIncrementsThePageNumber(): void
    {
        $strategy = new PagePaginationStrategy();
        $config = PaginationConfig::fromArray(['strategy' => 'page', 'limit' => 50, 'startPage' => 1]);

        self::assertSame(['page' => 1, 'limit' => 50], $strategy->firstPage($config)->query);
        self::assertSame(['page' => 2, 'limit' => 50], $strategy->nextPage($config, $this->state(0, 50))?->query);
        self::assertSame(['page' => 3, 'limit' => 50], $strategy->nextPage($config, $this->state(1, 50))?->query);
        self::assertNull($strategy->nextPage($config, $this->state(2, 10)));
    }

    #[Test]
    public function pageHonoursAZeroBasedStart(): void
    {
        $strategy = new PagePaginationStrategy();
        $config = PaginationConfig::fromArray(['strategy' => 'page', 'limit' => 50, 'startPage' => 0]);

        self::assertSame(['page' => 0, 'limit' => 50], $strategy->firstPage($config)->query);
        self::assertSame(['page' => 1, 'limit' => 50], $strategy->nextPage($config, $this->state(0, 50))?->query);
    }

    #[Test]
    public function cursorFollowsTheEmbeddedCursorUntilItRunsDry(): void
    {
        $strategy = new CursorPaginationStrategy(new RecordSelector());
        $config = PaginationConfig::fromArray([
            'strategy' => 'cursor',
            'limit' => 100,
            'cursorParam' => 'after',
            'cursorPath' => '$.meta.next',
        ]);

        self::assertSame(['limit' => 100], $strategy->firstPage($config)->query);

        $withCursor = new PageState(0, 100, $this->response(), ['meta' => ['next' => 'CUR-2']]);
        self::assertSame(['after' => 'CUR-2', 'limit' => 100], $strategy->nextPage($config, $withCursor)?->query);

        $noCursor = new PageState(1, 100, $this->response(), ['meta' => ['next' => null]]);
        self::assertNull($strategy->nextPage($config, $noCursor));

        $missing = new PageState(1, 100, $this->response(), ['data' => []]);
        self::assertNull($strategy->nextPage($config, $missing));
    }

    #[Test]
    public function linkHeaderFollowsRelNext(): void
    {
        $strategy = new LinkHeaderPaginationStrategy();
        $config = PaginationConfig::fromArray(['strategy' => 'link_header']);

        $withNext = new PageState(0, 100, $this->responseWithLink(
            '<https://api.example.com/p?page=2>; rel="next", <https://api.example.com/p?page=9>; rel="last"',
        ), []);
        self::assertSame('https://api.example.com/p?page=2', $strategy->nextPage($config, $withNext)?->url);

        $lastOnly = new PageState(1, 100, $this->responseWithLink(
            '<https://api.example.com/p?page=1>; rel="prev"',
        ), []);
        self::assertNull($strategy->nextPage($config, $lastOnly));
    }

    private function state(int $pageIndex, int $recordCount): PageState
    {
        return new PageState($pageIndex, $recordCount, $this->response(), []);
    }

    private function response(): GenericRestResponse
    {
        return new GenericRestResponse(200, [], '[]', 5, 2);
    }

    private function responseWithLink(string $link): GenericRestResponse
    {
        return new GenericRestResponse(200, ['link' => [$link]], '[]', 5, 2);
    }
}
