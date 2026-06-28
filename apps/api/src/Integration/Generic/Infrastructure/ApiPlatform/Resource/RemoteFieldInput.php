<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST input for `/api/remote_fields` (APIC-P2-05). `endpoint` is the parent
 * endpoint's UUID; the {@see \App\Integration\Generic\Infrastructure\ApiPlatform\State\RemoteFieldProcessor}
 * resolves it tenant-scoped and stamps the field's tenant from it.
 */
final class RemoteFieldInput
{
    #[Assert\NotBlank]
    #[Groups(['remote_field:create'])]
    public string $endpoint = '';

    #[Assert\NotBlank]
    #[Assert\Length(max: 512)]
    #[Groups(['remote_field:create'])]
    public string $path = '';

    #[Assert\Length(max: 255)]
    #[Groups(['remote_field:create'])]
    public ?string $label = null;

    #[Assert\Choice(choices: ['string', 'integer', 'number', 'boolean', 'object', 'array', 'null'])]
    #[Groups(['remote_field:create'])]
    public string $dataType = 'string';

    #[Assert\Length(max: 2048)]
    #[Groups(['remote_field:create'])]
    public ?string $sampleValue = null;
}
