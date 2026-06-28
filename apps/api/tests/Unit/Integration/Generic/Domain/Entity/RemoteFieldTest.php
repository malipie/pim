<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Domain\Entity;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Entity\RemoteField;
use App\Integration\Generic\Domain\Enum\RemoteEndpointRole;
use App\Integration\Generic\Domain\Enum\RemoteFieldDataType;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemoteFieldTest extends TestCase
{
    #[Test]
    public function newFieldDefaultsToStringWithoutLabelOrSample(): void
    {
        $f = $this->field();

        self::assertSame('$.sku', $f->getPath());
        self::assertSame(RemoteFieldDataType::String, $f->getDataType());
        self::assertNull($f->getLabel());
        self::assertNull($f->getSampleValue());
        self::assertNull($f->getTenant());
    }

    #[Test]
    public function exposesItsParentEndpoint(): void
    {
        $endpoint = $this->endpoint();
        $f = new RemoteField($endpoint, '$.id', RemoteFieldDataType::Integer);

        self::assertSame($endpoint, $f->getEndpoint());
        self::assertSame($endpoint->getId()->toRfc4122(), $f->getEndpointId()->toRfc4122());
    }

    #[Test]
    public function settersUpdateValues(): void
    {
        $f = $this->field();
        $f->setPath('$.price.amount');
        $f->setLabel('Price');
        $f->setDataType(RemoteFieldDataType::Number);
        $f->setSampleValue('19.99');

        self::assertSame('$.price.amount', $f->getPath());
        self::assertSame('Price', $f->getLabel());
        self::assertSame(RemoteFieldDataType::Number, $f->getDataType());
        self::assertSame('19.99', $f->getSampleValue());
    }

    #[Test]
    public function dataTypeKnowsScalarFromComposite(): void
    {
        self::assertTrue(RemoteFieldDataType::String->isScalar());
        self::assertTrue(RemoteFieldDataType::Integer->isScalar());
        self::assertTrue(RemoteFieldDataType::Number->isScalar());
        self::assertTrue(RemoteFieldDataType::Boolean->isScalar());
        self::assertFalse(RemoteFieldDataType::Object->isScalar());
        self::assertFalse(RemoteFieldDataType::Array->isScalar());
        self::assertFalse(RemoteFieldDataType::Null->isScalar());
    }

    #[Test]
    public function assignTenantIsWriteOnce(): void
    {
        $f = $this->field();
        $f->assignTenant(new Tenant('alpha', 'Alpha'));
        self::assertNotNull($f->getTenant());

        $this->expectException(LogicException::class);
        $f->assignTenant(new Tenant('beta', 'Beta'));
    }

    private function endpoint(): RemoteEndpoint
    {
        $connection = new Connection('idosell', 'IdoSell PL', 'https://api.idosell.com');

        return new RemoteEndpoint($connection, RemoteEndpointRole::ReadList, 'GET', '/products');
    }

    private function field(): RemoteField
    {
        return new RemoteField($this->endpoint(), '$.sku');
    }
}
