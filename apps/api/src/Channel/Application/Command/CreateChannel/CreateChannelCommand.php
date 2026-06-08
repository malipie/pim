<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\CreateChannel;

final readonly class CreateChannelCommand
{
    public function __construct(
        public string $code,
        public string $name,
        public ?string $categoryTreeRootId = null,
    ) {
    }
}
