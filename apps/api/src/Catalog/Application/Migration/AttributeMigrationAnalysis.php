<?php

declare(strict_types=1);

namespace App\Catalog\Application\Migration;

/**
 * UI-08.6 (#261) — read-side projection returned by the dry-run path of
 * `POST /api/attributes/{id}/migrate-type`.
 *
 * Carries the counts the admin UI needs to render the impact analyzer
 * (`#UI-08.12`) modal:
 *  - `rowCount`: number of `object_values` rows that would change.
 *  - `distinctValues`: number of unique input values in the corpus.
 *  - `mappings`: list of {from, to, count} entries built from the
 *     mapping plan + auto-detected entries.
 *  - `unmapped`: list of input values with no mapping target (per
 *     plan + auto-detection); count + sample.
 *  - `compatibility`: `safe | requires_force | blocked`.
 *
 * @phpstan-type MappingRow array{from: string, to: string, count: int}
 * @phpstan-type UnmappedRow array{value: string, count: int}
 */
final readonly class AttributeMigrationAnalysis
{
    /**
     * @param list<MappingRow>  $mappings
     * @param list<UnmappedRow> $unmapped
     */
    public function __construct(
        public string $compatibility,
        public int $rowCount,
        public int $distinctValues,
        public array $mappings,
        public array $unmapped,
        public bool $forceRequired,
        public ?string $blockedReason = null,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'compatibility' => $this->compatibility,
            'forceRequired' => $this->forceRequired,
            'blockedReason' => $this->blockedReason,
            'rowCount' => $this->rowCount,
            'distinctValues' => $this->distinctValues,
            'mappings' => $this->mappings,
            'unmapped' => $this->unmapped,
        ];
    }
}
