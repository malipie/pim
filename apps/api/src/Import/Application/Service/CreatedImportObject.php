<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Catalog\Domain\Entity\CatalogObject;

/**
 * IMP2-1.4 (#1466) — create() result: the new object plus any per-value
 * issues the BatchValueWriter collected (skipped values, never throws).
 */
final readonly class CreatedImportObject
{
    /**
     * @param list<array{attributeCode: string, kind: string, message: string}> $issues
     */
    public function __construct(
        public CatalogObject $object,
        public array $issues,
    ) {
    }
}
