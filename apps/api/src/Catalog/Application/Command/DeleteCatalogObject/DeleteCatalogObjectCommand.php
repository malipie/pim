<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DeleteCatalogObject;

use Symfony\Component\Uid\Uuid;

final readonly class DeleteCatalogObjectCommand
{
    public function __construct(
        public Uuid $id,
    ) {
    }
}
