<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\CreateNavigationTreeRoot;

use Symfony\Component\Uid\Uuid;

final readonly class CreateNavigationTreeRootCommand
{
    /**
     * @param array<string, string> $label
     */
    public function __construct(
        public Uuid $channelId,
        public string $code,
        public array $label,
        public ?string $externalCode = null,
    ) {
    }
}
