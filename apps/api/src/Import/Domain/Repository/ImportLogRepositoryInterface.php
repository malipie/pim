<?php

declare(strict_types=1);

namespace App\Import\Domain\Repository;

use App\Import\Domain\Entity\ImportLog;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportLogLevel;

interface ImportLogRepositoryInterface
{
    public function save(ImportLog $log): void;

    /**
     * @param list<ImportLogLevel> $levels filter; empty = all levels
     *
     * @return list<ImportLog>
     */
    public function findBySession(ImportSession $session, array $levels = [], int $limit = 1000): array;

    public function countBySession(ImportSession $session, ?ImportLogLevel $level = null): int;
}
