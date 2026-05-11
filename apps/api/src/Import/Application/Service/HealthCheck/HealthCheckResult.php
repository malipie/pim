<?php

declare(strict_types=1);

namespace App\Import\Application\Service\HealthCheck;

use App\Import\Domain\Enum\ImportSourceHealth;

final readonly class HealthCheckResult
{
    public function __construct(
        public ImportSourceHealth $health,
        public ?string $note,
        public int $latencyMs,
    ) {
    }

    public function isOk(): bool
    {
        return ImportSourceHealth::Ok === $this->health;
    }
}
