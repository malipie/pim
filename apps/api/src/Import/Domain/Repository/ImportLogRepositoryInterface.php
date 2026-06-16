<?php

declare(strict_types=1);

namespace App\Import\Domain\Repository;

use App\Import\Domain\Entity\ImportLog;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportLogLevel;
use Generator;

interface ImportLogRepositoryInterface
{
    public function save(ImportLog $log): void;

    /**
     * @param list<ImportLogLevel> $levels filter; empty = all levels
     *
     * @return list<ImportLog>
     */
    public function findBySession(ImportSession $session, array $levels = [], int $limit = 1000): array;

    /**
     * IMP2-2.7 (#1483) — memory-flat iterator over a session's logs for the
     * streamed CSV report (no full materialisation / no `setMaxResults` cap).
     *
     * @param list<ImportLogLevel> $levels filter; empty = all levels
     *
     * @return Generator<int, ImportLog>
     */
    public function iterateBySession(ImportSession $session, array $levels = []): Generator;

    public function countBySession(ImportSession $session, ?ImportLogLevel $level = null): int;
}
