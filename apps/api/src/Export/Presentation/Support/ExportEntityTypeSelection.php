<?php

declare(strict_types=1);

namespace App\Export\Presentation\Support;

use App\Export\Domain\Enum\ExportEntityType;
use Symfony\Component\Uid\Uuid;

/**
 * EXR-04 (#1380) — a validated `(entity_type, object_type_id)` pair parsed
 * from an export request payload.
 */
final readonly class ExportEntityTypeSelection
{
    public function __construct(
        public ExportEntityType $entityType,
        public ?Uuid $objectTypeId,
    ) {
    }
}
