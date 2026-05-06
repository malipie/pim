<?php

declare(strict_types=1);

namespace App\Import\Domain\ValueObject;

use App\Import\Domain\Enum\MappingConfidence;

/**
 * One row in the Step-2 mapping table. The wizard renders this as
 * "header → attribute" with a confidence badge plus sample values.
 */
final readonly class ColumnMappingSuggestion
{
    /**
     * @param list<string|null> $sampleValues
     */
    public function __construct(
        public int $columnIndex,
        public string $columnHeader,
        public ?string $suggestedAttributeCode,
        public MappingConfidence $confidence,
        public array $sampleValues,
        public ?string $alternativeAttributeCode = null,
    ) {
    }
}
