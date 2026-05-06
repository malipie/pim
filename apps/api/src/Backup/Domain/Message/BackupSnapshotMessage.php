<?php

declare(strict_types=1);

namespace App\Backup\Domain\Message;

use Symfony\Component\Uid\Uuid;

final readonly class BackupSnapshotMessage
{
    public function __construct(
        public Uuid $backupId,
        public Uuid $tenantId,
    ) {
    }
}
