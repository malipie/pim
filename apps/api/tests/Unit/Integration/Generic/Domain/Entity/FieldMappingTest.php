<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Domain\Entity;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\FieldMapping;
use App\Integration\Generic\Domain\Enum\MappingDirection;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class FieldMappingTest extends TestCase
{
    #[Test]
    public function newMappingDefaultsToInboundVersionOne(): void
    {
        $m = $this->mapping();

        self::assertSame('sku', $m->getPimTarget());
        self::assertSame('$.sku', $m->getRemoteFieldPath());
        self::assertSame(MappingDirection::Inbound, $m->getDirection());
        self::assertFalse($m->isMatchKey());
        self::assertSame(1, $m->getVersion());
        self::assertNull($m->getBindingId());
        self::assertNull($m->getTenant());
    }

    #[Test]
    public function exposesItsParentConnection(): void
    {
        $connection = $this->connection();
        $m = new FieldMapping($connection, 'name', '$.name');

        self::assertSame($connection, $m->getConnection());
        self::assertSame($connection->getId()->toRfc4122(), $m->getConnectionId()->toRfc4122());
    }

    #[Test]
    public function bumpVersionIncrementsForReuse(): void
    {
        $m = $this->mapping();
        $m->bumpVersion();
        $m->bumpVersion();

        self::assertSame(3, $m->getVersion());
    }

    #[Test]
    public function bindToLinksALooseSyncBinding(): void
    {
        $m = $this->mapping();
        $bindingId = Uuid::v7();

        $m->bindTo($bindingId);
        self::assertSame($bindingId->toRfc4122(), $m->getBindingId()?->toRfc4122());

        $m->bindTo(null);
        self::assertNull($m->getBindingId());
    }

    #[Test]
    public function settersUpdateValues(): void
    {
        $m = $this->mapping();
        $m->setPimTarget('status');
        $m->setRemoteFieldPath('$.state');
        $m->setDirection(MappingDirection::Both);
        $m->setMatchKey(true);

        self::assertSame('status', $m->getPimTarget());
        self::assertSame('$.state', $m->getRemoteFieldPath());
        self::assertSame(MappingDirection::Both, $m->getDirection());
        self::assertTrue($m->isMatchKey());
    }

    #[Test]
    public function directionKnowsInboundAndOutbound(): void
    {
        self::assertTrue(MappingDirection::Inbound->appliesInbound());
        self::assertFalse(MappingDirection::Inbound->appliesOutbound());
        self::assertFalse(MappingDirection::Outbound->appliesInbound());
        self::assertTrue(MappingDirection::Outbound->appliesOutbound());
        self::assertTrue(MappingDirection::Both->appliesInbound());
        self::assertTrue(MappingDirection::Both->appliesOutbound());
    }

    #[Test]
    public function assignTenantIsWriteOnce(): void
    {
        $m = $this->mapping();
        $m->assignTenant(new Tenant('alpha', 'Alpha'));
        self::assertNotNull($m->getTenant());

        $this->expectException(LogicException::class);
        $m->assignTenant(new Tenant('beta', 'Beta'));
    }

    private function connection(): Connection
    {
        return new Connection('idosell', 'IdoSell PL', 'https://api.idosell.com');
    }

    private function mapping(): FieldMapping
    {
        return new FieldMapping($this->connection(), 'sku', '$.sku');
    }
}
