<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application\Discovery;

use App\Integration\Generic\Application\Discovery\DiscoveredField;
use App\Integration\Generic\Application\Discovery\JsonFlattener;
use App\Integration\Generic\Domain\Enum\RemoteFieldDataType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonFlattenerTest extends TestCase
{
    private JsonFlattener $flattener;

    protected function setUp(): void
    {
        $this->flattener = new JsonFlattener();
    }

    #[Test]
    public function infersScalarTypesFromAFlatRecord(): void
    {
        $fields = $this->byPath([
            'sku' => 'A-1',
            'qty' => 7,
            'price' => 19.99,
            'active' => true,
            'note' => null,
        ]);

        self::assertSame(RemoteFieldDataType::String, $fields['$.sku']->dataType);
        self::assertSame('A-1', $fields['$.sku']->sampleValue);
        self::assertSame(RemoteFieldDataType::Integer, $fields['$.qty']->dataType);
        self::assertSame(RemoteFieldDataType::Number, $fields['$.price']->dataType);
        self::assertSame(RemoteFieldDataType::Boolean, $fields['$.active']->dataType);
        self::assertSame('true', $fields['$.active']->sampleValue);
        self::assertSame(RemoteFieldDataType::Null, $fields['$.note']->dataType);
        self::assertNull($fields['$.note']->sampleValue);
    }

    #[Test]
    public function walksNestedObjectsIntoDotPaths(): void
    {
        $fields = $this->byPath([
            'sku' => 'A-1',
            'price' => ['amount' => 1999, 'currency' => 'PLN'],
        ]);

        self::assertArrayHasKey('$.price.amount', $fields);
        self::assertArrayHasKey('$.price.currency', $fields);
        self::assertSame(RemoteFieldDataType::Integer, $fields['$.price.amount']->dataType);
        self::assertSame('PLN', $fields['$.price.currency']->sampleValue);
        // The intermediate object itself is not emitted as a field.
        self::assertArrayNotHasKey('$.price', $fields);
    }

    #[Test]
    public function treatsListsAsArrayLeaves(): void
    {
        $fields = $this->byPath([
            'tags' => ['new', 'sale'],
            'empty' => [],
        ]);

        self::assertSame(RemoteFieldDataType::Array, $fields['$.tags']->dataType);
        self::assertSame('["new","sale"]', $fields['$.tags']->sampleValue);
        // An empty array is a leaf (no keys to recurse), typed as Array.
        self::assertSame(RemoteFieldDataType::Array, $fields['$.empty']->dataType);
    }

    #[Test]
    public function truncatesLongSampleValues(): void
    {
        $fields = $this->byPath(['blob' => str_repeat('x', 500)]);

        self::assertNotNull($fields['$.blob']->sampleValue);
        self::assertSame(200, mb_strlen($fields['$.blob']->sampleValue));
    }

    #[Test]
    public function honoursACustomPrefix(): void
    {
        $fields = $this->flattener->flatten(['id' => 1], '$.data');

        self::assertSame('$.data.id', $fields[0]->path);
    }

    /**
     * @param array<array-key, mixed> $record
     *
     * @return array<string, DiscoveredField>
     */
    private function byPath(array $record): array
    {
        $byPath = [];
        foreach ($this->flattener->flatten($record) as $field) {
            $byPath[$field->path] = $field;
        }

        return $byPath;
    }
}
