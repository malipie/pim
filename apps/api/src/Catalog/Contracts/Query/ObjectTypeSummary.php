<?php

declare(strict_types=1);

namespace App\Catalog\Contracts\Query;

use App\Catalog\Domain\ObjectKind;
use Symfony\Component\Uid\Uuid;

/**
 * Cross-BC read projection of {@see \App\Catalog\Domain\Entity\ObjectType}.
 * Channel and Asset BCs pull this through GetObjectTypeSummary instead of
 * type-hinting the full Domain entity in their FK relations.
 */
final readonly class ObjectTypeSummary
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
        public bool $isBuiltIn,
    ) {
    }
}
