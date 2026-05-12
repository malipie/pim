<?php

declare(strict_types=1);

namespace App\Import\Domain\Enum;

enum ScheduleRunStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'error';
}
