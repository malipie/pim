<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH input shape for `/api/channel_object_type_mappings/{id}` (VIEW-06).
 * Only `targetField` is editable in MVP; isPublished toggle deferred.
 */
final class ChannelObjectTypeMappingPatchInput
{
    #[Assert\Length(max: 255)]
    #[Groups(['channel_mapping:patch'])]
    public ?string $targetField = null;
}
