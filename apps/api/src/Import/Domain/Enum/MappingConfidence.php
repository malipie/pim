<?php

declare(strict_types=1);

namespace App\Import\Domain\Enum;

/**
 * How confident the {@see \App\Import\Application\Service\AutoMapper} is
 * about a single column → attribute mapping.
 *
 * - Auto:   exact alias match in the dictionary.
 * - Fuzzy:  Levenshtein < 2 against an alias; UI surfaces "did you mean…".
 * - Manual: no rule fired; the user picks from the attribute dropdown.
 * - Skip:   user marked the column as ignored.
 */
enum MappingConfidence: string
{
    case Auto = 'auto';
    case Fuzzy = 'fuzzy';
    case Manual = 'manual';
    case Skip = 'skip';
}
