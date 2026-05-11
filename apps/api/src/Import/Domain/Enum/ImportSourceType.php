<?php

declare(strict_types=1);

namespace App\Import\Domain\Enum;

/**
 * VIEW-IMP-03 (#500) — transport type for an {@see \App\Import\Domain\Entity\ImportSource}.
 *
 * MVP probes:
 *   - `folder` is the only fully implemented driver (real readability check).
 *   - The rest are stubs that return `ok` with a "polling not enabled" note;
 *     the real probes ship with the polling daemon follow-up.
 */
enum ImportSourceType: string
{
    case Sftp = 'sftp';
    case Ftp = 'ftp';
    case Http = 'http';
    case Folder = 'folder';
    case Webhook = 'webhook';
    case Api = 'api';
    case Upload = 'upload';
}
