<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Repository;

use App\Integration\Generic\Domain\Entity\SyncRun;
use App\Integration\Generic\Domain\Entity\SyncRunLog;
use Symfony\Component\Uid\Uuid;

interface SyncRunLogRepositoryInterface
{
    public function save(SyncRunLog $log): void;

    public function findById(Uuid $id): ?SyncRunLog;

    /**
     * @return list<SyncRunLog>
     */
    public function findByRun(SyncRun $run): array;
}
