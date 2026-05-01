<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\UpdateAttributeGroup;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateAttributeGroupCommand
{
    /**
     * @param array<string, string>|null $label
     * @param array<string, string>|null $description
     */
    public function __construct(
        public Uuid $id,
        public ?array $label = null,
        public ?array $description = null,
        public ?string $icon = null,
        public ?string $color = null,
        public ?int $position = null,
        public bool $clearIcon = false,
        public bool $clearColor = false,
        public bool $clearDescription = false,
    ) {
    }
}
