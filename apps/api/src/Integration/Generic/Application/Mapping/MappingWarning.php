<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Mapping;

/**
 * A non-fatal mapping issue surfaced by {@see MappingValidator} (ADR-0022, epic
 * APIC, ticket APIC-P2-08) — e.g. a type that needs coercion or a path missing
 * from the discovered schema. Per ADR-0016 decision 5 these are warnings, not
 * errors: the 1:1 sync still runs with minimal coercion.
 */
final readonly class MappingWarning
{
    public function __construct(
        public string $pimTarget,
        public string $remoteFieldPath,
        public string $message,
    ) {
    }
}
