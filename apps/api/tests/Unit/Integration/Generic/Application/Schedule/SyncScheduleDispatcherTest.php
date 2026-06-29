<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application\Schedule;

use App\Integration\Generic\Application\Schedule\SyncScheduleCalculator;
use App\Integration\Generic\Application\Schedule\SyncScheduleDispatcher;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Enum\SyncDirection;
use App\Integration\Generic\Domain\Message\InboundSyncMessage;
use App\Integration\Generic\Domain\Message\OutboundSyncMessage;
use App\Shared\Domain\Tenant;
use App\Tests\Unit\Integration\Generic\Application\Subscriber\RecordingMessageBus;
use DateTimeImmutable;
use DateTimeZone;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

#[CoversClass(SyncScheduleDispatcher::class)]
final class SyncScheduleDispatcherTest extends TestCase
{
    private RecordingMessageBus $bus;
    private InMemorySyncBindingRepository $bindings;
    private SyncScheduleDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->bus = new RecordingMessageBus();
        $this->bindings = new InMemorySyncBindingRepository();
        $this->dispatcher = new SyncScheduleDispatcher(
            new SyncScheduleCalculator(maxJitterSeconds: 300),
            $this->bindings,
            $this->bus,
        );
    }

    private function binding(SyncDirection $direction, ?string $schedule, bool $withTenant = true): SyncBinding
    {
        $connection = new Connection('shop', 'Shop', 'https://api.example.test');
        $binding = new SyncBinding($connection, Uuid::v7(), $direction);
        $binding->setSchedule($schedule);
        if ($withTenant) {
            $binding->assignTenant(new Tenant('acme', 'Acme'));
        }

        return $binding;
    }

    public function testComputeNextRunSetsSlotForValidCron(): void
    {
        $binding = $this->binding(SyncDirection::Inbound, '0 2 * * *');

        $this->dispatcher->computeNextRun($binding, new DateTimeImmutable('2026-06-29 12:00:00', new DateTimeZone('UTC')));

        self::assertNotNull($binding->getNextRun());
        self::assertGreaterThanOrEqual(
            new DateTimeImmutable('2026-06-30 02:00:00', new DateTimeZone('UTC')),
            $binding->getNextRun(),
        );
        self::assertSame([$binding], $this->bindings->saved);
    }

    public function testComputeNextRunClearsSlotWhenNoSchedule(): void
    {
        $binding = $this->binding(SyncDirection::Inbound, null);

        $this->dispatcher->computeNextRun($binding);

        self::assertNull($binding->getNextRun());
        self::assertSame([$binding], $this->bindings->saved);
    }

    public function testComputeNextRunClearsSlotWhenCronInvalid(): void
    {
        $binding = $this->binding(SyncDirection::Inbound, 'not a cron');

        $this->dispatcher->computeNextRun($binding);

        self::assertNull($binding->getNextRun());
    }

    public function testDispatchInboundEnqueuesInboundMessageOnly(): void
    {
        $binding = $this->binding(SyncDirection::Inbound, '0 2 * * *');

        $this->dispatcher->dispatch($binding);

        self::assertCount(1, $this->bus->dispatched);
        $message = $this->bus->dispatched[0];
        self::assertInstanceOf(InboundSyncMessage::class, $message);
        self::assertSame($binding->getId()->toRfc4122(), $message->bindingId->toRfc4122());
        self::assertSame($binding->getTenant()?->getId()->toRfc4122(), $message->tenant->toRfc4122());
        // nextRun rolled forward after firing.
        self::assertNotNull($binding->getNextRun());
    }

    public function testDispatchOutboundEnqueuesOutboundMessageOnly(): void
    {
        $binding = $this->binding(SyncDirection::Outbound, '0 2 * * *');

        $this->dispatcher->dispatch($binding);

        self::assertCount(1, $this->bus->dispatched);
        self::assertInstanceOf(OutboundSyncMessage::class, $this->bus->dispatched[0]);
    }

    public function testDispatchBidirectionalEnqueuesBothLegs(): void
    {
        $binding = $this->binding(SyncDirection::Bidirectional, '0 2 * * *');

        $this->dispatcher->dispatch($binding);

        self::assertCount(2, $this->bus->dispatched);
        self::assertInstanceOf(InboundSyncMessage::class, $this->bus->dispatched[0]);
        self::assertInstanceOf(OutboundSyncMessage::class, $this->bus->dispatched[1]);
    }

    public function testDispatchWithoutTenantThrows(): void
    {
        $binding = $this->binding(SyncDirection::Inbound, '0 2 * * *', withTenant: false);

        $this->expectException(LogicException::class);

        $this->dispatcher->dispatch($binding);
    }
}
