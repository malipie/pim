<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\ApiPlatform\Resource;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * POST input shape for `/api/sync_bindings` (APIC-P3-10). The tenant-stamped
 * {@see \App\Integration\Generic\Domain\Entity\SyncBinding} is built by
 * {@see \App\Integration\Generic\Infrastructure\ApiPlatform\State\SyncBindingProcessor}.
 *
 * `connection` and the read/write endpoints are UUID references resolved
 * tenant-scoped by the processor. `objectTypeId` is a loose reference to a
 * Catalog ObjectType (no FK, per ADR-0022 / APIC-P3-01): only its UUID shape is
 * validated here.
 */
final class SyncBindingInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[Groups(['sync_binding:create'])]
    public string $connection = '';

    #[Assert\NotBlank]
    #[Assert\Uuid]
    #[Groups(['sync_binding:create'])]
    public string $objectTypeId = '';

    #[Assert\Choice(choices: ['inbound', 'outbound', 'bidirectional'])]
    #[Groups(['sync_binding:create'])]
    public string $direction = 'inbound';

    #[Assert\Uuid]
    #[Groups(['sync_binding:create'])]
    public ?string $readEndpoint = null;

    #[Assert\Uuid]
    #[Groups(['sync_binding:create'])]
    public ?string $writeEndpoint = null;

    #[Assert\Length(max: 255)]
    #[Groups(['sync_binding:create'])]
    public ?string $schedule = null;

    #[Assert\Choice(choices: ['lww', 'pim_wins', 'remote_wins'])]
    #[Groups(['sync_binding:create'])]
    public string $conflictPolicy = 'lww';

    #[Assert\Length(max: 255)]
    #[Groups(['sync_binding:create'])]
    public ?string $matchKeyMapping = null;

    #[Groups(['sync_binding:create'])]
    public bool $enabled = true;
}
