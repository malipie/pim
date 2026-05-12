<?php

declare(strict_types=1);

namespace App\Tests\Unit\Search\Application;

use App\Catalog\Domain\ObjectKind;
use App\Search\Application\CatalogIndexCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

/**
 * PROD-03 — collector dedup + supersede semantics.
 */
final class CatalogIndexCollectorTest extends TestCase
{
    #[Test]
    public function emptyCollectorDrainsToEmptyArrays(): void
    {
        $c = new CatalogIndexCollector();

        self::assertTrue($c->isEmpty());
        self::assertSame([], $c->drainUpsertIds());
        self::assertSame([], $c->drainDeletes());
    }

    #[Test]
    public function repeatedUpsertsForTheSameIdDedupToSingleEntry(): void
    {
        $c = new CatalogIndexCollector();
        $id = Uuid::v7();

        $c->queueUpsert($id);
        $c->queueUpsert($id);
        $c->queueUpsert($id);

        self::assertFalse($c->isEmpty());
        self::assertSame([$id->toRfc4122()], $c->drainUpsertIds());
    }

    #[Test]
    public function deleteSupersedesPendingUpsert(): void
    {
        $c = new CatalogIndexCollector();
        $id = Uuid::v7();

        $c->queueUpsert($id);
        $c->queueDelete($id, ObjectKind::Product);

        self::assertSame([], $c->drainUpsertIds(), 'upsert must be dropped when a delete lands later');
        self::assertSame([$id->toRfc4122() => ObjectKind::Product], $c->drainDeletes());
    }

    #[Test]
    public function upsertSupersedesPendingDelete(): void
    {
        $c = new CatalogIndexCollector();
        $id = Uuid::v7();

        $c->queueDelete($id, ObjectKind::Product);
        $c->queueUpsert($id);

        self::assertSame([], $c->drainDeletes(), 'delete must be dropped when an upsert lands later (recreate flow)');
        self::assertSame([$id->toRfc4122()], $c->drainUpsertIds());
    }

    #[Test]
    public function drainEmptiesTheBuffer(): void
    {
        $c = new CatalogIndexCollector();
        $id = Uuid::v7();
        $c->queueUpsert($id);

        $c->drainUpsertIds();

        self::assertTrue($c->isEmpty());
        self::assertSame([], $c->drainUpsertIds());
    }

    #[Test]
    public function resetClearsBothBuffers(): void
    {
        $c = new CatalogIndexCollector();
        $a = Uuid::v7();
        $b = Uuid::v7();
        $c->queueUpsert($a);
        $c->queueDelete($b, ObjectKind::Category);

        $c->reset();

        self::assertTrue($c->isEmpty());
        self::assertSame([], $c->drainUpsertIds());
        self::assertSame([], $c->drainDeletes());
    }
}
