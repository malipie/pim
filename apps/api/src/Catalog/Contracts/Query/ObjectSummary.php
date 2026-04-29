<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Query;

use App\Catalog\Domain\ObjectKind;
use Symfony\Component\Uid\Uuid;

/**
 * Cross-BC read projection of {@see \App\Catalog\Domain\Entity\CatalogObject}.
 *
 * The minimum surface other BCs need to validate or describe an object
 * without crossing into Catalog's Domain. RF-19 swaps the legacy
 * `Channel.categoryTreeRoot` / `Asset.object` `targetEntity` references
 * out for `Uuid` columns and resolves them through this DTO via the
 * GetObjectSummary query handler.
 */
final readonly class ObjectSummary
{
    /**
     * @param array<string, string> $label
     */
    public function __construct(
        public Uuid $id,
        public ObjectKind $kind,
        public string $code,
        public array $label,
        public Uuid $tenantId,
        public ?Uuid $parentId = null,
    ) {
    }
}
