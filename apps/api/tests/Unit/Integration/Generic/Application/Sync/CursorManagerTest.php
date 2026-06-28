<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application\Sync;

use App\Integration\Generic\Application\Sync\CursorManager;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Repository\SyncBindingRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class CursorManagerTest extends TestCase
{
    #[Test]
    public function advancesAndPersistsAtomicallyAfterABatch(): void
    {
        $binding = $this->binding(['field' => 'id', 'type' => 'incremental_id', 'state' => '100']);
        $repo = $this->createMock(SyncBindingRepositoryInterface::class);
        $repo->expects(self::once())->method('save')->with($binding);

        $advanced = new CursorManager($repo)->advance($binding, '250');

        self::assertTrue($advanced);
        self::assertSame('250', $binding->getCursor()['state'] ?? null);
    }

    #[Test]
    public function rejectsBackwardIncrementalId(): void
    {
        $binding = $this->binding(['field' => 'id', 'type' => 'incremental_id', 'state' => '500']);
        $repo = $this->createMock(SyncBindingRepositoryInterface::class);
        $repo->expects(self::never())->method('save');

        $advanced = new CursorManager($repo)->advance($binding, '499');

        self::assertFalse($advanced);
        // A crash-resume that replays an older page must not move the cursor back.
        self::assertSame('500', $binding->getCursor()['state'] ?? null);
    }

    #[Test]
    public function acceptsEqualValueForIdempotentReprocessing(): void
    {
        $binding = $this->binding(['field' => 'id', 'type' => 'incremental_id', 'state' => '500']);
        $repo = $this->createStub(SyncBindingRepositoryInterface::class);

        self::assertTrue(new CursorManager($repo)->advance($binding, '500'));
    }

    #[Test]
    public function comparesUpdatedAtTimestamps(): void
    {
        $binding = $this->binding(['field' => 'updated_at', 'type' => 'updated_at', 'state' => '2026-06-01T00:00:00+00:00']);
        $repo = $this->createStub(SyncBindingRepositoryInterface::class);
        $manager = new CursorManager($repo);

        self::assertTrue($manager->advance($binding, '2026-06-02T00:00:00+00:00'));
        self::assertFalse($manager->advance($binding, '2026-05-31T00:00:00+00:00'));
    }

    #[Test]
    public function rejectsUnparseableNewTimestamp(): void
    {
        $binding = $this->binding(['field' => 'updated_at', 'type' => 'updated_at', 'state' => '2026-06-01T00:00:00+00:00']);
        $repo = $this->createStub(SyncBindingRepositoryInterface::class);

        self::assertFalse(new CursorManager($repo)->advance($binding, 'not-a-date'));
    }

    #[Test]
    public function opaqueCursorAlwaysAdvances(): void
    {
        $binding = $this->binding(['field' => 'next', 'type' => 'opaque', 'state' => 'TOKEN-A']);
        $repo = $this->createStub(SyncBindingRepositoryInterface::class);

        self::assertTrue(new CursorManager($repo)->advance($binding, 'TOKEN-B'));
        self::assertSame('TOKEN-B', $binding->getCursor()['state'] ?? null);
    }

    #[Test]
    public function firstAdvanceAcceptsAnyValue(): void
    {
        $binding = $this->binding(['field' => 'id', 'type' => 'incremental_id']);
        $repo = $this->createStub(SyncBindingRepositoryInterface::class);

        self::assertTrue(new CursorManager($repo)->advance($binding, '42'));
    }

    #[Test]
    public function returnsFalseWhenNoCursorConfigured(): void
    {
        $binding = $this->binding(null);
        $repo = $this->createMock(SyncBindingRepositoryInterface::class);
        $repo->expects(self::never())->method('save');

        self::assertFalse(new CursorManager($repo)->advance($binding, '1'));
    }

    /**
     * @param array<string, mixed>|null $cursor
     */
    private function binding(?array $cursor): SyncBinding
    {
        $connection = new Connection('idosell', 'IdoSell PL', 'https://api.idosell.com');
        $binding = new SyncBinding($connection, Uuid::v7());
        $binding->setCursor($cursor);

        return $binding;
    }
}
