<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\UpdateAttribute;

use Symfony\Component\Uid\Uuid;

final readonly class UpdateAttributeCommand
{
    /**
     * @param array<string, string>|null $label
     * @param array<string, string>|null $help
     * @param array<string, mixed>|null  $validationRules
     * @param list<string>|null          $relationTargetObjectTypeIds
     * @param list<string>|null          $relationPreviewFields
     */
    public function __construct(
        public Uuid $id,
        public ?array $label = null,
        public ?array $help = null,
        public ?bool $localizable = null,
        public ?bool $scopable = null,
        public ?bool $required = null,
        public ?bool $filterable = null,
        public ?array $validationRules = null,
        public ?int $position = null,
        public ?array $relationTargetObjectTypeIds = null,
        public ?string $relationCardinality = null,
        public ?bool $relationAdvanced = null,
        public ?array $relationPreviewFields = null,
    ) {
    }
}
