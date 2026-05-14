<?php

declare(strict_types=1);

namespace App\Export\Domain\Repository;

use App\Export\Domain\Entity\ExportSession;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface ExportSessionRepositoryInterface
{
    public function save(ExportSession $session): void;

    public function findById(Uuid $id): ?ExportSession;

    /**
     * Self-audit query: list current user's exports (PRD §8.5).
     *
     * @return list<ExportSession>
     */
    public function findByTenantAndUser(Tenant $tenant, Uuid $userId, int $limit = 50): array;

    /**
     * Concurrent-jobs limiter (PRD §11.7, §12.1). Counts pending+running
     * sessions for a tenant — handler checks against per-tier cap before
     * dispatching a new job.
     */
    public function countActiveForTenant(Tenant $tenant): int;
}
