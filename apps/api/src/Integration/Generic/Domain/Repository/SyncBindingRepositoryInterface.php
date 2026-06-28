<?php

declare(strict_types=1);

namespace App\Integration\Generic\Domain\Repository;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use Symfony\Component\Uid\Uuid;

interface SyncBindingRepositoryInterface
{
    public function save(SyncBinding $binding): void;

    public function remove(SyncBinding $binding): void;

    public function findById(Uuid $id): ?SyncBinding;

    /**
     * @return list<SyncBinding>
     */
    public function findByConnection(Connection $connection): array;

    /**
     * @return list<SyncBinding>
     */
    public function findEnabled(): array;
}
