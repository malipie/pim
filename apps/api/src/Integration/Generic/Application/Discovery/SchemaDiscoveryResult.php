<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Discovery;

/**
 * The outcome of a schema discovery run (ADR-0022, epic APIC, ticket APIC-P2-04):
 * the proposed fields plus the raw sample record they were inferred from (so the
 * wizard can preview it side by side).
 */
final readonly class SchemaDiscoveryResult
{
    /**
     * @param list<DiscoveredField>   $fields
     * @param array<array-key, mixed> $sampleRecord
     * @param int                     $sampledRecords number of records the sample page held
     */
    public function __construct(
        public array $fields,
        public array $sampleRecord,
        public int $sampledRecords,
    ) {
    }

    public static function empty(): self
    {
        return new self([], [], 0);
    }
}
