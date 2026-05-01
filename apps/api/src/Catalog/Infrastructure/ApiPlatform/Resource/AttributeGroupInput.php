<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST input shape for `/api/attribute_groups` (#260 / UI-08.5). AP4
 * default Doctrine processor cannot hydrate the constructor-only
 * AttributeGroup aggregate — this DTO is the deserialisation target for
 * the State Processor.
 */
final class AttributeGroupInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex('/^[a-z0-9_-]+$/')]
    #[Groups(['attribute_group:create'])]
    public string $code = '';

    /**
     * @var array<string, string>
     */
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[Groups(['attribute_group:create'])]
    public array $label = [];

    /**
     * @var array<string, string>|null
     */
    #[Assert\Type('array')]
    #[Groups(['attribute_group:create'])]
    public ?array $description = null;

    #[Assert\Length(max: 64)]
    #[Groups(['attribute_group:create'])]
    public ?string $icon = null;

    #[Assert\Length(max: 16)]
    #[Groups(['attribute_group:create'])]
    public ?string $color = null;

    #[Assert\PositiveOrZero]
    #[Groups(['attribute_group:create'])]
    public int $position = 0;
}
