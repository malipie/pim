<?php

declare(strict_types=1);

namespace App\Import\Domain\Repository;

use App\Import\Domain\Entity\ImportSourceLog;

interface ImportSourceLogRepositoryInterface
{
    public function save(ImportSourceLog $log): void;
}
