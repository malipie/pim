<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Repository;

use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Entity\SyncRun;
use Symfony\Component\Uid\Uuid;

interface SyncRunRepositoryInterface
{
    public function save(SyncRun $run): void;

    public function findById(Uuid $id): ?SyncRun;

    /**
     * @return list<SyncRun>
     */
    public function findByBinding(SyncBinding $binding): array;
}
