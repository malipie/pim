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
     * Internal admin display name. Single-language by design — the channel
     * name is never published to a destination.
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['channel:create'])]
    public string $name = '';
}
