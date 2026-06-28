<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Enum;

/**
 * Lifecycle status of a {@see \App\Integration\Generic\Domain\Entity\SyncRun}
 * (ADR-0022, epic APIC, ticket APIC-P3-02; mirrors the `ScheduleRunStatus`
 * pattern).
 */
enum SyncRunStatus: string
{
    case Running = 'running';
    case Success = 'success';
    case Partial = 'partial';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return self::Running !== $this;
    }
}
