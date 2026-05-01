<?php

declare(strict_types=1);

namespace App\Catalog\Application\Query\GetObjectFormSchema;

/**
 * Read-side projection returned by {@see GetObjectFormSchemaHandler}.
 *
 * @phpstan-type AttributeRow array{
 *     id: string,
 *     code: string,
 *     type: string,
 *     label: array<string, string>,
 *     help: array<string, string>|null,
 *     is_localizable: bool,
 *     is_scopable: bool,
 *     is_required: bool,
 *     is_system: bool,
 *     position: int,
 *     is_required_in_group: bool,
 *     visible_when: array<string, mixed>|null,
 *     validation_rules: array<string, mixed>
 * }
 * @phpstan-type GroupRow array{
 *     id: string,
 *     code: string,
 *     label: array<string, string>,
 *     description: array<string, string>|null,
 *     icon: string|null,
 *     color: string|null,
 *     is_system_group: bool,
 *     auto_attached: bool,
 *     position: int,
 *     attributes: list<AttributeRow>
 * }
 */
final readonly class ObjectFormSchema
{
    /**
     * @param array{id: string, code: string, kind: string, label: array<string, string>} $objectType
     * @param list<array<string, mixed>>                                                  $effectiveGroups
     */
    public function __construct(
        public string $objectId,
        public array $objectType,
        public array $effectiveGroups,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'objectId' => $this->objectId,
            'objectType' => $this->objectType,
            'effectiveGroups' => $this->effectiveGroups,
        ];
    }
}
