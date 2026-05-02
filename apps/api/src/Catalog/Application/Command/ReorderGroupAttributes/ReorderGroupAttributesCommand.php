<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\ReorderGroupAttributes;

use Symfony\Component\Uid\Uuid;

final readonly class ReorderGroupAttributesCommand
{
    /**
     * @param list<string> $attributeCodesInOrder
     */
    public function __construct(
        public Uuid $attributeGroupId,
        public array $attributeCodesInOrder,
    ) {
    }
}
