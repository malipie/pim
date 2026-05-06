<?php

declare(strict_types=1);

namespace App\Backup\Domain\Enum;

enum BackupStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return self::Completed === $this || self::Failed === $this;
    }
}
