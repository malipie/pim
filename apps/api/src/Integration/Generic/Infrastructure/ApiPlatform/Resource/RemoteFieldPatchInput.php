<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * PATCH (RFC 7396 merge) input for `/api/remote_fields/{id}` (APIC-P2-05).
 * Every field is nullable — null means "leave unchanged". `endpoint` is
 * immutable and omitted.
 */
final class RemoteFieldPatchInput
{
    #[Assert\Length(max: 512)]
    #[Groups(['remote_field:patch'])]
    public ?string $path = null;

    #[Assert\Length(max: 255)]
    #[Groups(['remote_field:patch'])]
    public ?string $label = null;

    #[Assert\Choice(choices: ['string', 'integer', 'number', 'boolean', 'object', 'array', 'null'])]
    #[Groups(['remote_field:patch'])]
    public ?string $dataType = null;

    #[Assert\Length(max: 2048)]
    #[Groups(['remote_field:patch'])]
    public ?string $sampleValue = null;
}
