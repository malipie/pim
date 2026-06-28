<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Domain\Entity;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Enum\RemoteEndpointRole;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemoteEndpointTest extends TestCase
{
    #[Test]
    public function newEndpointDefaultsToNoPagingJsonResponse(): void
    {
        $e = $this->endpoint();

        self::assertSame(RemoteEndpointRole::ReadList, $e->getRole());
        self::assertSame('GET', $e->getHttpMethod());
        self::assertSame('/products', $e->getPathTemplate());
        self::assertSame([], $e->getQueryParams());
        self::assertNull($e->getRequestBodyTemplate());
        self::assertSame(['strategy' => 'none'], $e->getPagination());
        self::assertNull($e->getRecordSelector());
        self::assertSame('json', $e->getResponseFormat());
        self::assertNull($e->getTenant());
    }

    #[Test]
    public function exposesItsParentConnection(): void
    {
        $connection = $this->connection();
        $e = new RemoteEndpoint($connection, RemoteEndpointRole::ReadOne, 'GET', '/products/{id}');

        self::assertSame($connection, $e->getConnection());
        self::assertSame($connection->getId()->toRfc4122(), $e->getConnectionId()->toRfc4122());
    }

    #[Test]
    public function settersUpdateValues(): void
    {
        $e = $this->endpoint();
        $e->setRole(RemoteEndpointRole::WriteCreate);
        $e->setHttpMethod('POST');
        $e->setPathTemplate('/products');
        $e->setQueryParams(['limit' => '50']);
        $e->setRequestBodyTemplate(['sku' => '{{sku}}']);
        $e->setPagination(['strategy' => 'cursor', 'cursorParam' => 'after']);
        $e->setRecordSelector('$.data');
        $e->setResponseFormat('json');

        self::assertSame(RemoteEndpointRole::WriteCreate, $e->getRole());
        self::assertSame('POST', $e->getHttpMethod());
        self::assertSame(['limit' => '50'], $e->getQueryParams());
        self::assertSame(['sku' => '{{sku}}'], $e->getRequestBodyTemplate());
        self::assertSame(['strategy' => 'cursor', 'cursorParam' => 'after'], $e->getPagination());
        self::assertSame('$.data', $e->getRecordSelector());
    }

    #[Test]
    public function roleKnowsReadFromWrite(): void
    {
        self::assertTrue(RemoteEndpointRole::ReadList->isRead());
        self::assertTrue(RemoteEndpointRole::ReadOne->isRead());
        self::assertFalse(RemoteEndpointRole::WriteCreate->isRead());
        self::assertFalse(RemoteEndpointRole::WriteUpdate->isRead());
    }

    #[Test]
    public function assignTenantIsWriteOnce(): void
    {
        $e = $this->endpoint();
        $e->assignTenant(new Tenant('alpha', 'Alpha'));
        self::assertNotNull($e->getTenant());

        $this->expectException(LogicException::class);
        $e->assignTenant(new Tenant('beta', 'Beta'));
    }

    private function connection(): Connection
    {
        return new Connection('idosell', 'IdoSell PL', 'https://api.idosell.com');
    }

    private function endpoint(): RemoteEndpoint
    {
        return new RemoteEndpoint($this->connection(), RemoteEndpointRole::ReadList, 'GET', '/products');
    }
}
