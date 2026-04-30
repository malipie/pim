<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Application\Command\DeleteApiProfile;

use Symfony\Component\Uid\Uuid;

final readonly class DeleteApiProfileCommand
{
    public function __construct(
        public Uuid $id,
    ) {
    }
}
