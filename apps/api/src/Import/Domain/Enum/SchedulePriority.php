<?php

declare(strict_types=1);

namespace App\Import\Domain\Enum;

enum SchedulePriority: string
{
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';
}
