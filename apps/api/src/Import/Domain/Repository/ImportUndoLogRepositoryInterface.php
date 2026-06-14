<?php

declare(strict_types=1);

namespace App\Import\Domain\Repository;

use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Entity\ImportUndoLog;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface ImportUndoLogRepositoryInterface
{
    /** Persist without flushing — the import handler's flushAndClear commits it with the chunk. */
    public function add(ImportUndoLog $log): void;

    /**
     * All undo rows for the session, newest first (rollback replays in reverse
     * of capture order).
     *
     * @return list<ImportUndoLog>
     */
    public function findBySession(ImportSession $session): array;

    /**
     * Distinct object ids touched by the session's undo rows (the pre-existing
     * objects whose attributes_indexed / completeness / search doc must be
     * rebuilt after a rollback replay).
     *
     * @return list<Uuid>
     */
    public function affectedObjectIds(ImportSession $session): array;

    /**
     * Scope keys (objectId|attributeCode|locale|channelId) of this session's undo
     * rows that a LATER import session has since overwritten on the same cell —
     * rolling back would clobber the newer import, so the caller skips + reports
     * them. Provenance alone cannot distinguish two imports (both `import`).
     *
     * @return array<string, true>
     */
    public function supersededScopeKeys(ImportSession $session): array;

    /**
     * Row counts per {@see \App\Import\Domain\Enum\ImportUndoOperation} value,
     * for the rollback preview.
     *
     * @return array<string, int>
     */
    public function countByOperation(ImportSession $session): array;

    /**
     * Purge undo rows whose session's rollback window has fully closed (no
     * rollback possible any more), tenant-scoped via the caller's context.
     *
     * @return int rows deleted
     */
    public function purgeForClosedWindows(DateTimeImmutable $now, int $limit = 5000): int;
}
