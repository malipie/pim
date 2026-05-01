<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\CreateAttributeGroup;

final readonly class CreateAttributeGroupCommand
{
    /**
     * @param array<string, string>      $label
     * @param array<string, string>|null $description
     */
    public function __construct(
        public string $code,
        public array $label,
        public ?array $description = null,
        public ?string $icon = null,
        public ?string $color = null,
        public int $position = 0,
    ) {
    }
}
