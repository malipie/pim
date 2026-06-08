<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\UpdateChannel;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateChannelCommand
{
    /**
     * @param string|false|null $categoryTreeRootId pass `false` to leave unchanged, `null` or `''` to clear, string UUID to set
     */
    public function __construct(
        public Uuid $id,
        public ?string $name = null,
        public string|false|null $categoryTreeRootId = false,
    ) {
    }
}
