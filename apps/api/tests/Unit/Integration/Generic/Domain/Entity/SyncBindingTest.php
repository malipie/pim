<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Domain\Entity;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Enum\ConflictPolicy;
use App\Integration\Generic\Domain\Enum\RemoteEndpointRole;
use App\Integration\Generic\Domain\Enum\SyncDirection;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SyncBindingTest extends TestCase
{
    #[Test]
    public function newBindingDefaultsToInboundLwwEnabled(): void
    {
        $b = $this->binding();

        self::assertSame(SyncDirection::Inbound, $b->getDirection());
        self::assertSame(ConflictPolicy::Lww, $b->getConflictPolicy());
        self::assertTrue($b->isEnabled());
        self::assertNull($b->getSchedule());
        self::assertNull($b->getCursor());
        self::assertNull($b->getReadEndpoint());
        self::assertNull($b->getWriteEndpoint());
        self::assertNull($b->getMatchKeyMapping());
        self::assertNull($b->getTenant());
    }

    #[Test]
    public function exposesConnectionAndObjectType(): void
    {
        $connection = $this->connection();
        $objectTypeId = Uuid::v7();
        $b = new SyncBinding($connection, $objectTypeId);

        self::assertSame($connection->getId()->toRfc4122(), $b->getConnectionId()->toRfc4122());
        self::assertSame($objectTypeId->toRfc4122(), $b->getObjectTypeId()->toRfc4122());
    }

    #[Test]
    public function settersUpdateValues(): void
    {
        $b = $this->binding();
        $readEndpoint = new RemoteEndpoint($b->getConnection(), RemoteEndpointRole::ReadList, 'GET', '/products');
        $writeEndpoint = new RemoteEndpoint($b->getConnection(), RemoteEndpointRole::WriteCreate, 'POST', '/products');

        $b->setDirection(SyncDirection::Bidirectional);
        $b->setConflictPolicy(ConflictPolicy::RemoteWins);
        $b->setSchedule('0 */2 * * *');
        $b->setCursor(['field' => 'updated_at', 'type' => 'updated_at', 'state' => '2026-06-01']);
        $b->setMatchKeyMapping('sku');
        $b->setReadEndpoint($readEndpoint);
        $b->setWriteEndpoint($writeEndpoint);
        $b->setEnabled(false);

        self::assertSame(SyncDirection::Bidirectional, $b->getDirection());
        self::assertSame(ConflictPolicy::RemoteWins, $b->getConflictPolicy());
        self::assertSame('0 */2 * * *', $b->getSchedule());
        self::assertSame(['field' => 'updated_at', 'type' => 'updated_at', 'state' => '2026-06-01'], $b->getCursor());
        self::assertSame('sku', $b->getMatchKeyMapping());
        self::assertSame($readEndpoint, $b->getReadEndpoint());
        self::assertSame($writeEndpoint, $b->getWriteEndpoint());
        self::assertFalse($b->isEnabled());
    }

    #[Test]
    public function directionKnowsLegs(): void
    {
        self::assertTrue(SyncDirection::Inbound->readsRemote());
        self::assertFalse(SyncDirection::Inbound->writesRemote());
        self::assertFalse(SyncDirection::Outbound->readsRemote());
        self::assertTrue(SyncDirection::Outbound->writesRemote());
        self::assertTrue(SyncDirection::Bidirectional->readsRemote());
        self::assertTrue(SyncDirection::Bidirectional->writesRemote());
    }

    #[Test]
    public function assignTenantIsWriteOnce(): void
    {
        $b = $this->binding();
        $b->assignTenant(new Tenant('alpha', 'Alpha'));
        self::assertNotNull($b->getTenant());

        $this->expectException(LogicException::class);
        $b->assignTenant(new Tenant('beta', 'Beta'));
    }

    private function connection(): Connection
    {
        return new Connection('idosell', 'IdoSell PL', 'https://api.idosell.com');
    }

    private function binding(): SyncBinding
    {
        return new SyncBinding($this->connection(), Uuid::v7());
    }
}
