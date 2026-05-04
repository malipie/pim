<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH input shape for `/api/channels/{id}` (VIEW-06 #418). All fields
 * optional — partial update; `code` is immutable post-create so excluded.
 */
final class ChannelPatchInput
{
    /**
     * @var array<string, string>|null
     */
    #[Assert\Type('array')]
    #[Assert\Count(min: 1)]
    #[Groups(['channel:update'])]
    public ?array $label = null;

    /**
     * @var array<int, string>|null
     */
    #[Assert\Type('array')]
    #[Assert\Count(min: 1)]
    #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
    #[Groups(['channel:update'])]
    public ?array $locales = null;

    /**
     * @var array<int, string>|null
     */
    #[Assert\Type('array')]
    #[Assert\Count(min: 1)]
    #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
    #[Groups(['channel:update'])]
    public ?array $currencies = null;

    /**
     * Pass empty string `""` to clear; null = no change.
     */
    #[Groups(['channel:update'])]
    public ?string $categoryTreeRootId = null;
}
