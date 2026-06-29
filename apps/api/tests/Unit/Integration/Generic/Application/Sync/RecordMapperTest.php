<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application\Sync;

use App\Integration\Generic\Application\Sync\RecordMapper;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\FieldMapping;
use App\Integration\Generic\Domain\Enum\MappingDirection;
use App\Integration\Generic\Infrastructure\Http\RecordSelector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RecordMapperTest extends TestCase
{
    private RecordMapper $mapper;
    private Connection $connection;

    protected function setUp(): void
    {
        $this->mapper = new RecordMapper(new RecordSelector());
        $this->connection = new Connection('idosell', 'IdoSell', 'https://api.idosell.com');
    }

    #[Test]
    public function mapsMatchKeyAndScalarValues(): void
    {
        $mappings = [
            $this->mapping('sku', '$.sku', MappingDirection::Inbound, isMatchKey: true),
            $this->mapping('name', '$.title', MappingDirection::Inbound),
            $this->mapping('price', '$.price.amount', MappingDirection::Both),
        ];
        $record = ['sku' => 'A-1', 'title' => 'Widget', 'price' => ['amount' => 1999]];

        $mapped = $this->mapper->map($record, $mappings);

        self::assertNotNull($mapped);
        self::assertSame('sku', $mapped->matchAttributeCode);
        self::assertSame('A-1', $mapped->matchValue);
        self::assertSame(['sku' => 'A-1', 'name' => 'Widget', 'price' => 1999], $mapped->attributeValues);
    }

    #[Test]
    public function returnsNullWithoutAMatchKey(): void
    {
        $mappings = [$this->mapping('name', '$.title', MappingDirection::Inbound)];

        self::assertNull($this->mapper->map(['title' => 'Widget'], $mappings));
    }

    #[Test]
    public function returnsNullWhenMatchValueMissing(): void
    {
        $mappings = [$this->mapping('sku', '$.sku', MappingDirection::Inbound, isMatchKey: true)];

        self::assertNull($this->mapper->map(['title' => 'Widget'], $mappings));
    }

    #[Test]
    public function skipsCompositeValuesAndOutboundOnlyMappings(): void
    {
        $mappings = [
            $this->mapping('sku', '$.sku', MappingDirection::Inbound, isMatchKey: true),
            $this->mapping('tags', '$.tags', MappingDirection::Inbound),         // array → skipped
            $this->mapping('secret', '$.secret', MappingDirection::Outbound),    // outbound → ignored
        ];
        $record = ['sku' => 'A-1', 'tags' => ['new', 'sale'], 'secret' => 'x'];

        $mapped = $this->mapper->map($record, $mappings);

        self::assertNotNull($mapped);
        self::assertSame(['sku' => 'A-1'], $mapped->attributeValues);
    }

    private function mapping(
        string $pimTarget,
        string $remotePath,
        MappingDirection $direction,
        bool $isMatchKey = false,
    ): FieldMapping {
        $mapping = new FieldMapping($this->connection, $pimTarget, $remotePath, $direction);
        $mapping->setMatchKey($isMatchKey);

        return $mapping;
    }
}
