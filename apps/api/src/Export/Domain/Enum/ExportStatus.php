<?php

declare(strict_types=1);

namespace App\Export\Domain\Enum;

/**
 * Lifecycle of a single export session (PRD §5.1).
 *
 * `pending` is the row's default right after dispatch; the worker
 * transitions through `running` to a terminal state. No paused/cancelled
 * states in MVP — exports are read-only operations that either complete
 * or fail, there is nothing to roll back.
 */
enum ExportStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Done = 'done';
    case Error = 'error';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Done, self::Error => true,
            default => false,
        };
    }

    public function isDownloadable(): bool
    {
        return self::Done === $this;
    }
}
