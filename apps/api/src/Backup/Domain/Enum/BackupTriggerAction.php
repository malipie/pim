<?php

declare(strict_types=1);

namespace App\Backup\Domain\Enum;

enum BackupTriggerAction: string
{
    case Manual = 'manual';
    case PreImport = 'pre_import';
    case Scheduled = 'scheduled';
}
