<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportSessionStatus;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-IMP-01 — rolling-window throughput for the sessions hero card.
 *
 * Returns the per-second rate of rows processed across the operator's
 * currently active import sessions (running + paused). Window defaults
 * to 5 minutes; clamped to [1, 60].
 *
 * No new schema — aggregates already-tracked counters
 * (successCount + errorCount) divided by elapsed-since-start. The
 * caller polls every ~5s from the FE; the FE-side KPI strip computes
 * its own derived numbers from the listing payload.
 */
final class ImportThroughputCalculator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function calculate(Tenant $tenant, Uuid $userId, int $windowMin = 5): ThroughputSnapshot
    {
        $this->guardWindow($windowMin);

        $now = new DateTimeImmutable();
        $cutoff = $now->modify(\sprintf('-%d minutes', $windowMin));

        $qb = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(ImportSession::class, 's')
            ->where('s.tenant = :tenant')
            ->andWhere('s.userId = :userId')
            ->andWhere('s.status IN (:active)')
            ->andWhere('s.startedAt IS NOT NULL')
            ->setParameter('tenant', $tenant)
            ->setParameter('userId', $userId)
            ->setParameter('active', [
                ImportSessionStatus::Running->value,
                ImportSessionStatus::Paused->value,
            ]);

        /** @var list<ImportSession> $active */
        $active = $qb->getQuery()->getResult();

        return self::aggregate($active, $cutoff, $now, $windowMin);
    }

    /**
     * Pure rolling-window aggregation — exposed so the unit tests
     * can drive the math without standing up a database. Each
     * session contributes (successCount + errorCount) rows over the
     * elapsed seconds between max(startedAt, cutoff) and `now`.
     *
     * @param list<ImportSession> $active
     */
    public static function aggregate(
        array $active,
        DateTimeImmutable $cutoff,
        DateTimeImmutable $now,
        int $windowMin,
    ): ThroughputSnapshot {
        $totalProcessed = 0;
        $totalElapsedSec = 0.0;

        foreach ($active as $session) {
            $startedAt = $session->getStartedAt();
            if (!$startedAt instanceof DateTimeImmutable) {
                continue;
            }
            $windowStart = $startedAt > $cutoff ? $startedAt : $cutoff;
            $elapsed = max(1.0, (float) ($now->getTimestamp() - $windowStart->getTimestamp()));
            $totalProcessed += $session->getSuccessCount() + $session->getErrorCount();
            $totalElapsedSec += $elapsed;
        }

        $rowsPerSec = $totalElapsedSec > 0 ? $totalProcessed / $totalElapsedSec : 0.0;

        return new ThroughputSnapshot(
            rowsPerSec: $rowsPerSec,
            activeSessions: \count($active),
            windowMin: $windowMin,
            sampledAt: $now,
        );
    }

    private function guardWindow(int $windowMin): void
    {
        if ($windowMin < 1 || $windowMin > 60) {
            throw new InvalidArgumentException('windowMin must be between 1 and 60.');
        }
    }
}
