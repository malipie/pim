<?php

declare(strict_types=1);

namespace App\Import\Application\Service\HealthCheck;

use App\Import\Domain\Entity\ImportSource;
use App\Import\Domain\Enum\ImportSourceType;

interface HealthCheckDriverInterface
{
    public function supports(ImportSourceType $type): bool;

    public function probe(ImportSource $source): HealthCheckResult;
}
