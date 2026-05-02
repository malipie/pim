<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\DeleteAttribute;

use Symfony\Component\Uid\Uuid;

final readonly class DeleteAttributeCommand
{
    public function __construct(public Uuid $id)
    {
    }
}
