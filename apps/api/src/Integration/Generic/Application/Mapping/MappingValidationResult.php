<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Mapping;

/**
 * The outcome of validating a connection's field mappings (ADR-0022, epic APIC,
 * ticket APIC-P2-08): blocking errors (e.g. a missing match key for inbound)
 * plus non-fatal type warnings. `isValid()` is true when there are no errors.
 */
final readonly class MappingValidationResult
{
    /**
     * @param list<string>         $errors
     * @param list<MappingWarning> $warnings
     */
    public function __construct(
        public array $errors,
        public array $warnings,
    ) {
    }

    public function isValid(): bool
    {
        return [] === $this->errors;
    }
}
