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
    #[Assert\Length(max: 255)]
    #[Assert\NotBlank(allowNull: true)]
    #[Groups(['channel:update'])]
    public ?string $name = null;
}
