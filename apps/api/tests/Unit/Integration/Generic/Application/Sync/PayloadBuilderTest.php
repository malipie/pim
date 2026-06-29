<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application\Sync;

use App\Integration\Generic\Application\Sync\PayloadBuilder;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\FieldMapping;
use App\Integration\Generic\Domain\Enum\MappingDirection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PayloadBuilderTest extends TestCase
{
    private PayloadBuilder $builder;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->builder = new PayloadBuilder();
        $this->connection = new Connection('idosell', 'IdoSell', 'https://api.idosell.com');
    }

    #[Test]
    public function buildsNestedBodyFromOutboundMappings(): void
    {
        $mappings = [
            $this->mapping('sku', '$.sku', MappingDirection::Outbound),
            $this->mapping('name', '$.title', MappingDirection::Both),
            $this->mapping('price', '$.price.amount', MappingDirection::Outbound),
        ];
        $values = ['sku' => 'A-1', 'name' => 'Widget', 'price' => '1999'];

        $body = $this->builder->build($values, $mappings);

        self::assertSame([
            'sku' => 'A-1',
            'title' => 'Widget',
            'price' => ['amount' => '1999'],
        ], $body);
    }

    #[Test]
    public function ignoresInboundOnlyMappingsAndMissingValues(): void
    {
        $mappings = [
            $this->mapping('sku', '$.sku', MappingDirection::Outbound),
            $this->mapping('secret', '$.secret', MappingDirection::Inbound), // inbound-only → ignored
            $this->mapping('gone', '$.gone', MappingDirection::Outbound),     // no value → skipped
        ];
        $values = ['sku' => 'A-1', 'secret' => 'x'];

        $body = $this->builder->build($values, $mappings);

        self::assertSame(['sku' => 'A-1'], $body);
    }

    private function mapping(string $pimTarget, string $remotePath, MappingDirection $direction): FieldMapping
    {
        return new FieldMapping($this->connection, $pimTarget, $remotePath, $direction);
    }
}
