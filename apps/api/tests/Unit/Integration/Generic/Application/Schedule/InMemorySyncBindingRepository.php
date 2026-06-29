<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application\Schedule;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Repository\SyncBindingRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * In-memory {@see SyncBindingRepositoryInterface} for the schedule dispatcher
 * unit tests — records `save()` calls so `nextRun` recomputation can be asserted
 * without a database.
 */
final class InMemorySyncBindingRepository implements SyncBindingRepositoryInterface
{
    /** @var list<SyncBinding> */
    public array $saved = [];

    /** @var list<SyncBinding> */
    public array $due = [];

    public function save(SyncBinding $binding): void
    {
        $this->saved[] = $binding;
    }

    public function remove(SyncBinding $binding): void
    {
    }

    public function findById(Uuid $id): ?SyncBinding
    {
        return null;
    }

    public function findByConnection(Connection $connection): array
    {
        return [];
    }

    public function findEnabled(): array
    {
        return [];
    }

    public function findDueForScheduling(DateTimeImmutable $now): array
    {
        return $this->due;
    }
}
