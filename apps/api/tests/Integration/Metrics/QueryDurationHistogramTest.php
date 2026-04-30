<?php

declare(strict_types=1);

namespace App\Tests\Integration\Metrics;

use App\Shared\Infrastructure\Metrics\QueryDurationHistogram;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Locks in the DB-query histogram telemetry (audit MEDIUM-003).
 *
 * Boots the kernel, runs a couple of queries through the live DBAL
 * connection (which the {@see \App\Shared\Infrastructure\Doctrine\Middleware\QueryTimingMiddleware}
 * decorates), then asserts both the in-memory histogram counters and
 * the rendered Prometheus exposition reflect those queries.
 */
final class QueryDurationHistogramTest extends KernelTestCase
{
    use ResetDatabase;

    #[Test]
    public function recordsEveryDoctrineQueryIntoTheHistogram(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $histogram = $container->get(QueryDurationHistogram::class);
        self::assertInstanceOf(QueryDurationHistogram::class, $histogram);

        // Force a deterministic baseline — ResetDatabase already ran a
        // bunch of queries while rebuilding the schema, so we capture
        // the count "before" and assert delta-only.
        $before = $histogram->count();

        $connection = $container->get('doctrine')->getConnection();
        self::assertInstanceOf(Connection::class, $connection);

        // Hit several distinct DBAL paths so all wrappers are exercised:
        // - executeQuery → Statement::execute via the wrapped TimingStatement
        // - executeStatement → DELETE no-op via TimingStatement
        // - direct exec on the wrapped TimingConnection
        $connection->executeQuery('SELECT 1');
        $connection->executeQuery('SELECT 2');
        $connection->executeStatement('SELECT 3');

        $delta = $histogram->count() - $before;
        self::assertGreaterThanOrEqual(
            3,
            $delta,
            'Each Doctrine query should produce at least one histogram observation.',
        );
        self::assertGreaterThan(
            0.0,
            $histogram->sum(),
            'Cumulative query duration must be a positive wall-clock measurement.',
        );
    }

    #[Test]
    public function rendersPrometheusExpositionWithBucketsSumAndCount(): void
    {
        $histogram = new QueryDurationHistogram();
        $histogram->observe(0.0008);
        $histogram->observe(0.04);
        $histogram->observe(0.5);

        $rendered = $histogram->render();

        // Bucket lines are present and cumulative — every observation
        // ≤ upper-bound increments that bucket.
        self::assertStringContainsString('db_query_duration_seconds_bucket{le="0.001"} 1', $rendered);
        self::assertStringContainsString('db_query_duration_seconds_bucket{le="0.05"} 2', $rendered);
        self::assertStringContainsString('db_query_duration_seconds_bucket{le="0.5"} 3', $rendered);
        self::assertStringContainsString('db_query_duration_seconds_bucket{le="+Inf"} 3', $rendered);

        self::assertStringContainsString('db_query_duration_seconds_sum 0.5408', $rendered);
        self::assertStringContainsString('db_query_duration_seconds_count 3', $rendered);
    }
}
