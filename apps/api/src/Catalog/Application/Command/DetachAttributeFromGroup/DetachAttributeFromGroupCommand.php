<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DetachAttributeFromGroup;

use Symfony\Component\Uid\Uuid;

final readonly class DetachAttributeFromGroupCommand
{
    public function __construct(
        public Uuid $attributeGroupId,
        public Uuid $attributeId,
    ) {
    }
}
