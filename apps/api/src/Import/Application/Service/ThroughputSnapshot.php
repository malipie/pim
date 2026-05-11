<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use DateTimeImmutable;

/**
 * Immutable result emitted by {@see ImportThroughputCalculator}.
 */
final readonly class ThroughputSnapshot
{
    public function __construct(
        public float $rowsPerSec,
        public int $activeSessions,
        public int $windowMin,
        public DateTimeImmutable $sampledAt,
    ) {
    }
}
