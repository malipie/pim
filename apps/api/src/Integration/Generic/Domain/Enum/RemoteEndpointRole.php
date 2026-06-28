<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Enum;

/**
 * The role a {@see \App\Integration\Generic\Domain\Entity\RemoteEndpoint} plays
 * in a sync — the consumer-side analogue of an Airbyte stream operation
 * (ADR-0022, epic APIC).
 *
 * `read_*` endpoints pull from the remote (inbound sync); `write_*` endpoints
 * push to it (outbound sync). The pairing is what lets one Connection both
 * import and export against the same external API.
 */
enum RemoteEndpointRole: string
{
    case ReadList = 'read_list';
    case ReadOne = 'read_one';
    case WriteCreate = 'write_create';
    case WriteUpdate = 'write_update';

    /** Whether this role reads from the remote (inbound), as opposed to writing. */
    public function isRead(): bool
    {
        return self::ReadList === $this || self::ReadOne === $this;
    }
}
