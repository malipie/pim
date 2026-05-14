<?php

declare(strict_types=1);

namespace App\Export\Domain\Repository;

use App\Export\Domain\Entity\ExportLog;
use App\Export\Domain\Entity\ExportSession;

interface ExportLogRepositoryInterface
{
    public function save(ExportLog $log): void;

    /**
     * @return list<ExportLog>
     */
    public function findRecentForSession(ExportSession $session, int $limit = 100): array;
}
