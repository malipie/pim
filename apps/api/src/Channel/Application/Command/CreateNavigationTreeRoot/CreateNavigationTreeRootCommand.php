<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\CreateNavigationTreeRoot;

use Symfony\Component\Uid\Uuid;

final readonly class CreateNavigationTreeRootCommand
{
    /**
     * @param array<string, string> $label
     * @param string|null           $code  null/empty → handler defaults it to the new root's
     *                                     uuid-hex (the editor never sends a code; an internal
     *                                     slug must be unique per channel — and a channel may
     *                                     have several roots)
     */
    public function __construct(
        public Uuid $channelId,
        public ?string $code,
        public array $label,
        public ?string $externalCode = null,
    ) {
    }
}
