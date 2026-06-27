<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Enum;

/**
 * Lifecycle state of a {@see \App\Integration\Generic\Domain\Entity\Connection}.
 *
 * `draft` is the initial state right after creation (before a successful
 * connection test); `active`/`paused` are operator-controlled; `error` is set
 * by the health check / sync runtime when the remote is unreachable or auth
 * fails.
 */
enum ConnectionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Error = 'error';
}
