<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Metrics;

/**
 * In-memory Prometheus histogram for Doctrine DBAL query durations
 * (audit MEDIUM-003).
 *
 * Logging is OFF in production (memory discipline — sekcja 3.10 of the
 * architecture), but ops still needs to see when a query starts taking
 * meaningfully longer. The histogram aggregates each query's wall-clock
 * runtime into bucketed counters that Prometheus scrapes every N
 * seconds, so p95 / p99 alerts can be wired to `histogram_quantile`
 * without re-enabling SQL logging.
 *
 * State lives on the worker — same lifetime as
 * {@see MetricsController} reads
 * from `memory_get_usage()`. With multiple FrankenPHP workers behind
 * Caddy, scraping reaches one randomly; alerts watch the rolling max
 * across scrapes (same caveat as the worker memory metric).
 */
final class QueryDurationHistogram
{
    /**
     * Bucket upper bounds in seconds. Aligned with Prometheus client_php
     * defaults; covers sub-millisecond cache hits up to 10s long-running
     * reports.
     *
     * @var list<float>
     */
    public const array DEFAULT_BUCKETS = [
        0.001,
        0.005,
        0.01,
        0.025,
        0.05,
        0.1,
        0.25,
        0.5,
        1.0,
        2.5,
        5.0,
        10.0,
    ];

    /** @var list<float> */
    private array $buckets;

    /** @var array<int, int> bucket index → cumulative count */
    private array $bucketCounts;

    private int $count = 0;

    private float $sum = 0.0;

    /**
     * @param list<float>|null $buckets ascending bucket boundaries; null
     *                                  uses {@see DEFAULT_BUCKETS}
     */
    public function __construct(?array $buckets = null)
    {
        $this->buckets = $buckets ?? self::DEFAULT_BUCKETS;
        $this->bucketCounts = array_fill(0, \count($this->buckets), 0);
    }

    public function observe(float $durationSeconds): void
    {
        ++$this->count;
        $this->sum += $durationSeconds;

        foreach ($this->buckets as $index => $upperBound) {
            if ($durationSeconds <= $upperBound) {
                ++$this->bucketCounts[$index];
            }
        }
    }

    /**
     * Render a Prometheus exposition snippet (no leading TYPE/HELP — the
     * caller composes them). Histogram bucket lines use the canonical
     * `le="<upper>"` label including the `+Inf` overflow bucket. The
     * cumulative `_count` is identical to the `+Inf` bucket per spec.
     */
    public function render(): string
    {
        $lines = [];
        foreach ($this->buckets as $index => $upperBound) {
            $lines[] = \sprintf(
                'db_query_duration_seconds_bucket{le="%s"} %d',
                self::formatBucketBound($upperBound),
                $this->bucketCounts[$index],
            );
        }
        $lines[] = \sprintf('db_query_duration_seconds_bucket{le="+Inf"} %d', $this->count);
        $lines[] = \sprintf('db_query_duration_seconds_sum %s', self::formatFloat($this->sum));
        $lines[] = \sprintf('db_query_duration_seconds_count %d', $this->count);

        return implode("\n", $lines);
    }

    public function count(): int
    {
        return $this->count;
    }

    public function sum(): float
    {
        return $this->sum;
    }

    /**
     * @return list<float>
     */
    public function buckets(): array
    {
        return $this->buckets;
    }

    /**
     * @return array<int, int>
     */
    public function bucketCounts(): array
    {
        return $this->bucketCounts;
    }

    private static function formatBucketBound(float $value): string
    {
        // Match Prometheus convention: short decimals without trailing
        // zeros so `0.005` stays `0.005` and `1.0` becomes `1`.
        $formatted = rtrim(rtrim(\sprintf('%.6f', $value), '0'), '.');

        return '' === $formatted ? '0' : $formatted;
    }

    private static function formatFloat(float $value): string
    {
        return rtrim(rtrim(\sprintf('%.6f', $value), '0'), '.');
    }
}
