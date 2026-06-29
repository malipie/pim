<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Integration;

/**
 * One catalog object serialised for an outbound push (APIC-P3-06, ADR-0022).
 *
 * `values` is `attributeCode => serialized scalar` (built by the Export engine's
 * cell serializer); `objectId` identifies the source object for the run log.
 * The Integration sync maps these codes to remote field paths without touching
 * any Catalog/Export domain type.
 */
final readonly class OutboundRecord
{
    /**
     * @param array<string, string> $values
     */
    public function __construct(
        public string $objectId,
        public array $values,
    ) {
    }
}
