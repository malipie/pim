<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\CreateAttribute;

final readonly class CreateAttributeCommand
{
    /**
     * @param array<string, string>      $label
     * @param array<string, string>|null $help
     * @param array<string, mixed>       $validationRules
     * @param list<string>               $attachToGroups
     */
    public function __construct(
        public string $code,
        public array $label,
        public string $type,
        public ?array $help = null,
        public bool $localizable = false,
        public bool $scopable = false,
        public bool $required = false,
        public array $validationRules = [],
        public int $position = 0,
        public array $attachToGroups = [],
    ) {
    }
}
