<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Domain\Entity;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Entity\SyncRun;
use App\Integration\Generic\Domain\Enum\SyncDirection;
use App\Integration\Generic\Domain\Enum\SyncRunStatus;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class SyncRunTest extends TestCase
{
    #[Test]
    public function newRunStartsRunningWithZeroCounts(): void
    {
        $run = $this->makeRun();

        self::assertSame(SyncRunStatus::Running, $run->getStatus());
        self::assertSame(SyncDirection::Inbound, $run->getDirection());
        self::assertNull($run->getFinishedAt());
        self::assertSame(0, $run->getCreatedCount());
        self::assertSame(0, $run->getUpdatedCount());
        self::assertSame(0, $run->getSkippedCount());
        self::assertSame(0, $run->getFailedCount());
    }

    #[Test]
    public function countersIncrement(): void
    {
        $run = $this->makeRun();
        $run->recordCreated();
        $run->recordCreated();
        $run->recordUpdated();
        $run->recordSkipped();
        $run->recordFailed();

        self::assertSame(2, $run->getCreatedCount());
        self::assertSame(1, $run->getUpdatedCount());
        self::assertSame(1, $run->getSkippedCount());
        self::assertSame(1, $run->getFailedCount());
    }

    #[Test]
    public function finishDerivesSuccessWhenNoFailures(): void
    {
        $run = $this->makeRun();
        $run->recordCreated();
        $run->markFinished(null, ['state' => '2026-06-02']);

        self::assertSame(SyncRunStatus::Success, $run->getStatus());
        self::assertNotNull($run->getFinishedAt());
        self::assertSame(['state' => '2026-06-02'], $run->getCursorAfter());
    }

    #[Test]
    public function finishDerivesPartialWhenSomeFailed(): void
    {
        $run = $this->makeRun();
        $run->recordCreated();
        $run->recordFailed();
        $run->markFinished();

        self::assertSame(SyncRunStatus::Partial, $run->getStatus());
    }

    #[Test]
    public function finishDerivesFailedWhenNothingSucceeded(): void
    {
        $run = $this->makeRun();
        $run->recordFailed();
        $run->markFinished();

        self::assertSame(SyncRunStatus::Failed, $run->getStatus());
    }

    #[Test]
    public function cursorBeforeIsStored(): void
    {
        $run = $this->makeRun();
        $run->setCursorBefore(['state' => '2026-06-01']);

        self::assertSame(['state' => '2026-06-01'], $run->getCursorBefore());
    }

    #[Test]
    public function assignTenantIsWriteOnce(): void
    {
        $run = $this->makeRun();
        $run->assignTenant(new Tenant('alpha', 'Alpha'));
        self::assertNotNull($run->getTenant());

        $this->expectException(LogicException::class);
        $run->assignTenant(new Tenant('beta', 'Beta'));
    }

    private function makeRun(): SyncRun
    {
        $connection = new Connection('idosell', 'IdoSell PL', 'https://api.idosell.com');
        $binding = new SyncBinding($connection, Uuid::v7());

        return new SyncRun($binding, SyncDirection::Inbound);
    }
}
