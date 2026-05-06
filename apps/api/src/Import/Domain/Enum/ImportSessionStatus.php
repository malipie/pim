<?php

declare(strict_types=1);

namespace App\Import\Domain\Enum;

/**
 * Lifecycle of a single import session.
 *
 * `pending` is the row's default right after `POST /api/import-sessions`;
 * the worker transitions through `running` and lands on one of the
 * terminal states. `partial` differs from `success` by a non-zero
 * `error_count` (skipped rows in the report); both leave imported
 * objects in place. `rolled_back` is reachable from `success` / `partial`
 * within the 24h window guarded by `import_sessions.rollback_until`.
 */
enum ImportSessionStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Paused = 'paused';
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case RolledBack = 'rolled_back';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Success, self::Partial, self::Failed, self::Cancelled, self::RolledBack => true,
            default => false,
        };
    }

    public function isRollbackable(): bool
    {
        return self::Success === $this || self::Partial === $this;
    }
}
