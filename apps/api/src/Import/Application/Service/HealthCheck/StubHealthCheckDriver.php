<?php

declare(strict_types=1);

namespace App\Import\Application\Service\HealthCheck;

use App\Import\Domain\Entity\ImportSource;
use App\Import\Domain\Enum\ImportSourceHealth;
use App\Import\Domain\Enum\ImportSourceType;

/**
 * VIEW-IMP-03 (#500) — placeholder driver for transports whose real
 * probe ships with the polling daemon follow-up (sftp / ftp / http /
 * webhook / api / upload). Returns "ok" with an explanatory note so the
 * UI does not show a red health dot for non-folder sources.
 */
final class StubHealthCheckDriver implements HealthCheckDriverInterface
{
    /**
     * @param list<ImportSourceType> $types
     */
    public function __construct(private readonly array $types)
    {
    }

    public function supports(ImportSourceType $type): bool
    {
        return \in_array($type, $this->types, true);
    }

    public function probe(ImportSource $source): HealthCheckResult
    {
        return new HealthCheckResult(
            ImportSourceHealth::Ok,
            \sprintf(
                'Probe for %s sources will be implemented with the polling daemon follow-up.',
                $source->getType()->value,
            ),
            0,
        );
    }
}
