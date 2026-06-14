<?php

declare(strict_types=1);

namespace App\Import\Domain\Repository;

use App\Import\Domain\Entity\StagedFile;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface StagedFileRepositoryInterface
{
    public function save(StagedFile $stagedFile): void;

    public function remove(StagedFile $stagedFile): void;

    /**
     * Owner-scoped lookup: returns the staged file only when it belongs to
     * BOTH the given tenant and user. Any other combination yields null so
     * callers surface a 404 (no cross-tenant / cross-user reuse).
     */
    public function findOwned(Uuid $id, Tenant $tenant, Uuid $userId): ?StagedFile;

    /**
     * Staged files of a tenant created strictly before the cutoff — the
     * purge command's TTL sweep (tenant-scoped, never cross-tenant).
     *
     * @return list<StagedFile>
     */
    public function findExpired(Tenant $tenant, DateTimeImmutable $createdBefore, int $limit = 500): array;
}
