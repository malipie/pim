<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Discovery;

use App\Integration\Generic\Domain\Enum\RemoteFieldDataType;

/**
 * A field proposed by {@see SchemaDiscoveryService} from a response sample
 * (ADR-0022, epic APIC, ticket APIC-P2-04).
 *
 * It is a proposal, not a persisted {@see \App\Integration\Generic\Domain\Entity\RemoteField}:
 * the wizard (APIC-P2-06) lets the user accept/edit before the CRUD endpoint
 * (APIC-P2-05) saves the accepted ones.
 */
final readonly class DiscoveredField
{
    public function __construct(
        public string $path,
        public RemoteFieldDataType $dataType,
        public ?string $sampleValue,
    ) {
    }
}
