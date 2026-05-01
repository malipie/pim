<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH input shape for `/api/attribute_groups/{id}` (#260 / UI-08.5).
 *
 * `code` intentionally omitted — every AttributeGroup code is immutable
 * after create (`AttributeGroup::changeCode()` only allows non-system
 * groups, but at the API layer we keep things simple and refuse all
 * code changes; tenants rename via delete+recreate when needed).
 *
 * `description`/`icon`/`color` accept null to clear; the processor
 * reads the JSON document directly to distinguish "field absent" from
 * "field set to null" (PHP nullable property loses that distinction).
 */
final class AttributeGroupPatchInput
{
    /**
     * @var array<string, string>|null
     */
    #[Assert\Type('array')]
    #[Groups(['attribute_group:patch'])]
    public ?array $label = null;

    /**
     * @var array<string, string>|null
     */
    #[Assert\Type('array')]
    #[Groups(['attribute_group:patch'])]
    public ?array $description = null;

    #[Assert\Length(max: 64)]
    #[Groups(['attribute_group:patch'])]
    public ?string $icon = null;

    #[Assert\Length(max: 16)]
    #[Groups(['attribute_group:patch'])]
    public ?string $color = null;

    #[Assert\PositiveOrZero]
    #[Groups(['attribute_group:patch'])]
    public ?int $position = null;
}
