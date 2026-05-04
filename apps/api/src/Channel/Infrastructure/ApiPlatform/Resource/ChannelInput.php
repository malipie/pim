<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST input shape for `/api/channels` (VIEW-06 #418). AP4 default Doctrine
 * processor cannot hydrate the constructor-only Channel aggregate — this
 * DTO is the deserialisation target for the ChannelProcessor.
 */
final class ChannelInput
{
    #[Assert\NotBlank]
    #[Assert\Length(max: 64)]
    #[Assert\Regex('/^[a-z0-9_]+$/')]
    #[Groups(['channel:create'])]
    public string $code = '';

    /**
     * Multi-language label keyed by locale code. At least one entry required.
     *
     * @var array<string, string>
     */
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[Assert\Count(min: 1)]
    #[Groups(['channel:create'])]
    public array $label = [];

    /**
     * Locale codes (BCP-47 shape, e.g. `pl_PL`).
     *
     * @var array<int, string>
     */
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[Assert\Count(min: 1)]
    #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
    #[Groups(['channel:create'])]
    public array $locales = [];

    /**
     * Currency codes (ISO 4217, e.g. `PLN`).
     *
     * @var array<int, string>
     */
    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[Assert\Count(min: 1)]
    #[Assert\All([new Assert\Type('string'), new Assert\NotBlank()])]
    #[Groups(['channel:create'])]
    public array $currencies = [];

    #[Assert\Uuid(strict: false)]
    #[Groups(['channel:create'])]
    public ?string $categoryTreeRootId = null;
}
