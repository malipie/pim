<?php

declare(strict_types=1);

namespace App\Import\Domain\Repository;

use App\Import\Domain\Entity\ImportSchedule;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface ImportScheduleRepositoryInterface
{
    public function save(ImportSchedule $schedule): void;

    public function remove(ImportSchedule $schedule): void;

    public function findById(Uuid $id): ?ImportSchedule;

    public function findByCode(Tenant $tenant, string $code): ?ImportSchedule;

    /**
     * @return list<ImportSchedule>
     */
    public function findByTenant(Tenant $tenant): array;

    /**
     * @return list<ImportSchedule>
     */
    public function findUpcoming(Tenant $tenant, DateTimeImmutable $until): array;
}
