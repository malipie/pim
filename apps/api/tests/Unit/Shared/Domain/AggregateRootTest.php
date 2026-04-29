<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Domain;

use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\DomainEvent;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AggregateRootTest extends TestCase
{
    #[Test]
    public function recordsEventsAndReturnsThemInOrder(): void
    {
        $aggregate = new TestAggregate();
        $aggregate->doSomething('a');
        $aggregate->doSomething('b');

        $events = $aggregate->pullEvents();

        self::assertCount(2, $events);
        $first = $events[0];
        $second = $events[1];
        self::assertInstanceOf(TestEventRecorded::class, $first);
        self::assertInstanceOf(TestEventRecorded::class, $second);
        self::assertSame('a', $first->payload);
        self::assertSame('b', $second->payload);
    }

    #[Test]
    public function pullClearsTheBufferSoSubsequentPullsAreEmpty(): void
    {
        $aggregate = new TestAggregate();
        $aggregate->doSomething('only-once');

        $first = $aggregate->pullEvents();
        $second = $aggregate->pullEvents();

        self::assertCount(1, $first);
        self::assertSame([], $second);
    }

    #[Test]
    public function freshAggregateHasNoPendingEvents(): void
    {
        $aggregate = new TestAggregate();

        self::assertSame([], $aggregate->pullEvents());
    }
}

/**
 * Minimal aggregate stub used to drive the base class behaviour. Lives in
 * the same file as the test so it does not pollute production autoload.
 */
final class TestAggregate extends AggregateRoot
{
    public function doSomething(string $payload): void
    {
        $this->recordThat(new TestEventRecorded($payload));
    }
}

final readonly class TestEventRecorded implements DomainEvent
{
    private DateTimeImmutable $occurredOn;

    public function __construct(public string $payload)
    {
        $this->occurredOn = new DateTimeImmutable();
    }

    public function occurredOn(): DateTimeImmutable
    {
        return $this->occurredOn;
    }

    public function eventName(): string
    {
        return 'test.event-recorded';
    }

    public function aggregateId(): string
    {
        return $this->payload;
    }
}
