<?php

declare(strict_types=1);

namespace App\Import\Domain\Repository;

use App\Import\Domain\Entity\ImportScheduleRun;
use Symfony\Component\Uid\Uuid;

interface ImportScheduleRunRepositoryInterface
{
    public function save(ImportScheduleRun $run): void;

    /**
     * @return list<ImportScheduleRun>
     */
    public function findByScheduleId(Uuid $scheduleId, int $limit = 50): array;
}
