<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\CreateChannel;

final readonly class CreateChannelCommand
{
    /**
     * @param array<string, string> $label
     * @param array<int, string>    $localeCodes
     * @param array<int, string>    $currencyCodes
     */
    public function __construct(
        public string $code,
        public array $label,
        public array $localeCodes,
        public array $currencyCodes,
        public ?string $categoryTreeRootId = null,
    ) {
    }
}
