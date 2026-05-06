<?php

declare(strict_types=1);

namespace App\Backup\Domain\Repository;

use App\Backup\Domain\Entity\Backup;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface BackupRepositoryInterface
{
    public function save(Backup $backup): void;

    public function findById(Uuid $id): ?Backup;

    public function countSince(Tenant $tenant, DateTimeImmutable $since): int;
}
