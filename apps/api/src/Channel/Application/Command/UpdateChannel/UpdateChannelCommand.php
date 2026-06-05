<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\UpdateChannel;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateChannelCommand
{
    /**
     * @param array<string, string>|null $label
     * @param array<int, string>|null    $localeCodes
     * @param string|false|null          $categoryTreeRootId pass `false` to leave unchanged, `null` or `''` to clear, string UUID to set
     */
    public function __construct(
        public Uuid $id,
        public ?array $label = null,
        public ?array $localeCodes = null,
        public string|false|null $categoryTreeRootId = false,
    ) {
    }
}
