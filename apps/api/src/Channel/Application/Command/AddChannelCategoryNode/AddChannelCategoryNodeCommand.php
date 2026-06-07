<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\AddChannelCategoryNode;

use Symfony\Component\Uid\Uuid;

final readonly class AddChannelCategoryNodeCommand
{
    /**
     * @param array<string, string> $label
     * @param string|null           $code  null/empty → handler defaults it to the new node's
     *                                     uuid-hex (CHC-09: the tree editor never sends a code;
     *                                     it is an internal slug, unique per channel)
     */
    public function __construct(
        public Uuid $channelId,
        public Uuid $parentId,
        public ?string $code,
        public array $label,
        public ?string $externalCode = null,
    ) {
    }
}
