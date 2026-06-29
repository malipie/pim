<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application\Subscriber;

use App\Catalog\Contracts\BulkGuard;
use App\Catalog\Contracts\Event\ObjectAttributesChanged;
use App\Integration\Generic\Application\Subscriber\OutboundTriggerSubscriber;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Enum\SyncDirection;
use App\Integration\Generic\Domain\Message\OutboundSyncMessage;
use App\Integration\Generic\Domain\Repository\SyncBindingRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class OutboundTriggerSubscriberTest extends TestCase
{
    #[Test]
    public function enqueuesOnlyMatchingOutboundBindings(): void
    {
        $productType = Uuid::v7();
        $categoryType = Uuid::v7();
        $connection = new Connection('idosell', 'IdoSell', 'https://api.idosell.com');

        $bindings = [
            $this->binding($connection, $productType, SyncDirection::Outbound),     // match
            $this->binding($connection, $productType, SyncDirection::Bidirectional), // match
            $this->binding($connection, $productType, SyncDirection::Inbound),       // inbound → no
            $this->binding($connection, $categoryType, SyncDirection::Outbound),     // other type → no
        ];

        $bus = $this->bus();
        $this->subscriber($bus, $bindings, bulk: false)(
            new ObjectAttributesChanged(Uuid::v7(), Uuid::v7(), ['name'], objectTypeId: $productType),
        );

        self::assertCount(2, $bus->dispatched);
        self::assertContainsOnlyInstancesOf(OutboundSyncMessage::class, $bus->dispatched);
    }

    #[Test]
    public function skipsDuringBulk(): void
    {
        $bus = $this->bus();
        $type = Uuid::v7();
        $binding = $this->binding(new Connection('c', 'C', 'https://x'), $type, SyncDirection::Outbound);

        $this->subscriber($bus, [$binding], bulk: true)(
            new ObjectAttributesChanged(Uuid::v7(), Uuid::v7(), [], objectTypeId: $type),
        );

        self::assertSame([], $bus->dispatched);
    }

    #[Test]
    public function skipsWhenEventHasNoObjectType(): void
    {
        $bus = $this->bus();
        $binding = $this->binding(new Connection('c', 'C', 'https://x'), Uuid::v7(), SyncDirection::Outbound);

        $this->subscriber($bus, [$binding], bulk: false)(
            new ObjectAttributesChanged(Uuid::v7(), Uuid::v7(), []),
        );

        self::assertSame([], $bus->dispatched);
    }

    private function binding(Connection $connection, Uuid $objectTypeId, SyncDirection $direction): SyncBinding
    {
        return new SyncBinding($connection, $objectTypeId, $direction);
    }

    /**
     * @param list<SyncBinding> $bindings
     */
    private function subscriber(RecordingMessageBus $bus, array $bindings, bool $bulk): OutboundTriggerSubscriber
    {
        $guard = $this->createStub(BulkGuard::class);
        $guard->method('isBulk')->willReturn($bulk);

        $repo = $this->createStub(SyncBindingRepositoryInterface::class);
        $repo->method('findEnabled')->willReturn($bindings);

        return new OutboundTriggerSubscriber($guard, $repo, $bus);
    }

    private function bus(): RecordingMessageBus
    {
        return new RecordingMessageBus();
    }
}
