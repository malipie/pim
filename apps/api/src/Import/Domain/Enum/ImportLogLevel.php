<?php

declare(strict_types=1);

namespace App\Import\Domain\Enum;

enum ImportLogLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';
}
