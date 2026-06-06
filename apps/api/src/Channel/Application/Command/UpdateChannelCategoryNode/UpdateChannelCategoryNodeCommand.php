<?php

declare(strict_types=1);

namespace App\Channel\Application\Command\UpdateChannelCategoryNode;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateChannelCategoryNodeCommand
{
    /**
     * @param array<string, string>|null $label              null = leave unchanged
     * @param bool                       $changeExternalCode true = apply $externalCode (which may be null to clear)
     */
    public function __construct(
        public Uuid $channelId,
        public Uuid $nodeId,
        public ?array $label,
        public bool $changeExternalCode,
        public ?string $externalCode,
    ) {
    }
}
