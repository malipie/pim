<?php

declare(strict_types=1);

namespace App\Identity\Domain\Repository;

use App\Identity\Domain\Entity\AuditLog;

interface AuditLogRepositoryInterface
{
    public function save(AuditLog $entry): void;
}
