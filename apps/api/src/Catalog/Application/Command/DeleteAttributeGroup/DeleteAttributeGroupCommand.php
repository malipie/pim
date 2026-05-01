<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DeleteAttributeGroup;

use Symfony\Component\Uid\Uuid;

final readonly class DeleteAttributeGroupCommand
{
    public function __construct(
        public Uuid $id,
    ) {
    }
}
