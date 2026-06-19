<?php

declare(strict_types=1);

namespace App\Export\Domain\Repository;

use App\Export\Domain\Entity\ExportSession;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
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

    /**
     * Delete the session row. CASCADE on the DB schema removes any
     * `export_logs` attached to it; the MinIO file is the controller's
     * responsibility (EXP-08 download path).
     */
    public function remove(ExportSession $session): void;

    /**
     * AUD-050 (W2-11) — retention sweep: sessions for $tenant whose
     * `started_at` is older than $olderThan, oldest first, capped at $limit so
     * a tenant with a huge export backlog is drained across several command
     * runs (FrankenPHP worker-mode memory: bounded batch + the caller clears
     * the EntityManager between tenants).
     *
     * @return list<ExportSession>
     */
    public function findOlderThan(Tenant $tenant, DateTimeImmutable $olderThan, int $limit = 500): array;
}
