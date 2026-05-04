<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\PatchChannelObjectTypeMapping;

use Symfony\Component\Uid\Uuid;

final readonly class PatchChannelObjectTypeMappingCommand
{
    public function __construct(
        public Uuid $id,
        public ?string $targetField = null,
    ) {
    }
}
