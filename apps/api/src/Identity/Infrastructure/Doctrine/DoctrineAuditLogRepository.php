<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine;

use App\Identity\Domain\Entity\AuditLog;
use App\Identity\Domain\Repository\AuditLogRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * RBAC-P3-013 (#676) — append-only audit log repository.
 *
 * `save()` persists and flushes immediately because the listener calls
 * us on `kernel.response` — the entity manager state is not guaranteed
 * to be flushed by subsequent code. A dedicated flush per audit entry
 * keeps the contract simple; throughput optimisation (batch flush,
 * Messenger async) is a Phase 6 #720 benchmark concern.
 *
 * No update / delete API — audit entries are immutable by design.
 * Retention pruning runs through the existing `pim:audit:cleanup`
 * command on a schedule.
 */
final readonly class DoctrineAuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(AuditLog $entry): void
    {
        $this->entityManager->persist($entry);
        $this->entityManager->flush();
    }
}
