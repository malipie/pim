<?php

declare(strict_types=1);

namespace App\Import\Domain\Enum;

enum ImportSourceHealth: string
{
    case Ok = 'ok';
    case Warn = 'warn';
    case Error = 'error';
    case Off = 'off';
}
