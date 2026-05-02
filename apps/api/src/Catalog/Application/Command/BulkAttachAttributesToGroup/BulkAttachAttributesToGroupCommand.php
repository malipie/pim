<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\BulkAttachAttributesToGroup;

use Symfony\Component\Uid\Uuid;

final readonly class BulkAttachAttributesToGroupCommand
{
    /**
     * @param list<string> $attributeCodes
     */
    public function __construct(
        public Uuid $attributeGroupId,
        public array $attributeCodes,
    ) {
    }
}
