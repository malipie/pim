<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Sync;

/**
 * A remote record reduced to its PIM upsert shape by {@see RecordMapper}
 * (APIC-P3-04): the match attribute + value that identify the catalog object,
 * plus the scalar attribute values to write.
 */
final readonly class MappedRecord
{
    /**
     * @param array<string, scalar> $attributeValues attributeCode => scalar
     */
    public function __construct(
        public string $matchAttributeCode,
        public string $matchValue,
        public array $attributeValues,
    ) {
    }
}
