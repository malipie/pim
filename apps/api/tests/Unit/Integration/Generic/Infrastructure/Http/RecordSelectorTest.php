<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Infrastructure\Http;

use App\Integration\Generic\Infrastructure\Http\RecordSelector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecordSelectorTest extends TestCase
{
    private RecordSelector $selector;

    protected function setUp(): void
    {
        $this->selector = new RecordSelector();
    }

    #[Test]
    public function rootSelectorReturnsATopLevelList(): void
    {
        $body = [['sku' => 'A'], ['sku' => 'B']];

        self::assertSame($body, $this->selector->records($body, '$'));
        self::assertSame($body, $this->selector->records($body, null));
    }

    #[Test]
    public function dottedSelectorDigsIntoTheEnvelope(): void
    {
        $body = ['results' => [['sku' => 'A'], ['sku' => 'B']]];

        self::assertSame($body['results'], $this->selector->records($body, '$.results'));
        self::assertSame($body['results'], $this->selector->records($body, 'results'));
    }

    #[Test]
    public function nestedSelectorWalksMultipleSegments(): void
    {
        $body = ['data' => ['items' => [['id' => 1]]]];

        self::assertSame([['id' => 1]], $this->selector->records($body, '$.data.items'));
    }

    #[Test]
    public function singleObjectIsWrappedAsOneRecord(): void
    {
        $body = ['sku' => 'A', 'name' => 'Widget'];

        self::assertSame([$body], $this->selector->records($body, '$'));
    }

    #[Test]
    public function missingPathYieldsNoRecords(): void
    {
        self::assertSame([], $this->selector->records(['results' => []], '$.missing'));
        self::assertSame([], $this->selector->records(null, '$.results'));
        self::assertSame([], $this->selector->records('not json', '$'));
    }

    #[Test]
    public function valueResolvesAScalarForCursorLookups(): void
    {
        $body = ['meta' => ['next_cursor' => 'abc123']];

        self::assertSame('abc123', $this->selector->value($body, '$.meta.next_cursor'));
        self::assertNull($this->selector->value($body, '$.meta.missing'));
    }
}
