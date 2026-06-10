<?php

declare(strict_types=1);

namespace App\Export\Domain\Enum;

/**
 * Lifecycle of a single export session (PRD §5.1).
 *
 * `pending` is the row's default right after dispatch; the worker
 * transitions through `running` to a terminal state. `cancelled` (EXR-15)
 * is user-requested: the async handler checks the persisted status
 * between chunks and stops gracefully — exports are read-only, so there
 * is nothing to roll back beyond removing the partial temp file.
 */
enum ExportStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Error = 'error';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Done, self::Error, self::Cancelled => true,
            default => false,
        };
    }

    public function isDownloadable(): bool
    {
        return self::Done === $this;
    }
}
