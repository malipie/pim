<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\Middleware;

use App\Shared\Infrastructure\Metrics\QueryDurationHistogram;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Middleware;

/**
 * DBAL Driver middleware that times every query via wrapper
 * Connection / Statement objects and records into the in-memory
 * {@see QueryDurationHistogram} (audit MEDIUM-003).
 *
 * Tagged with `doctrine.middleware` (autoconfigured) — Symfony's
 * Doctrine bundle resolves the chain so this wraps the real driver
 * without touching `doctrine.yaml`.
 */
final readonly class QueryTimingMiddleware implements Middleware
{
    public function __construct(private QueryDurationHistogram $histogram)
    {
    }

    public function wrap(Driver $driver): Driver
    {
        return new TimingDriver($driver, $this->histogram);
    }
}
