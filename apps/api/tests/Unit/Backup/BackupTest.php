<?php

declare(strict_types=1);

namespace App\Tests\Unit\Backup;

use App\Backup\Domain\Entity\Backup;
use App\Backup\Domain\Enum\BackupStatus;
use App\Backup\Domain\Enum\BackupTriggerAction;
use App\Shared\Domain\Tenant;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class BackupTest extends TestCase
{
    #[Test]
    public function constructorDefaultsToPending(): void
    {
        $backup = $this->makeBackup();

        self::assertSame(BackupStatus::Pending, $backup->getStatus());
        self::assertSame(BackupTriggerAction::PreImport, $backup->getTriggeredByAction());
        self::assertNull($backup->getCompletedAt());
        self::assertNull($backup->getErrorMessage());
    }

    #[Test]
    public function happyPathPendingRunningCompleted(): void
    {
        $backup = $this->makeBackup();
        $backup->markRunning();
        $backup->markCompleted(sizeBytes: 12_345_678, pgbackrestLabel: '20260506-191124F');

        self::assertSame(BackupStatus::Completed, $backup->getStatus());
        self::assertSame(12_345_678, $backup->getSizeBytes());
        self::assertSame('20260506-191124F', $backup->getPgbackrestLabel());
        self::assertNotNull($backup->getCompletedAt());
    }

    #[Test]
    public function failedFromRunningKeepsErrorMessage(): void
    {
        $backup = $this->makeBackup();
        $backup->markRunning();
        $backup->markFailed('disk full');

        self::assertSame(BackupStatus::Failed, $backup->getStatus());
        self::assertSame('disk full', $backup->getErrorMessage());
        self::assertNotNull($backup->getCompletedAt());
    }

    #[Test]
    public function cannotCompleteFromPending(): void
    {
        $backup = $this->makeBackup();

        $this->expectException(LogicException::class);
        $backup->markCompleted(sizeBytes: 1);
    }

    #[Test]
    public function tenantCannotBeReassigned(): void
    {
        $backup = $this->makeBackup();
        $backup->assignTenant(new Tenant('demo-1', 'Demo 1'));

        $this->expectException(LogicException::class);
        $backup->assignTenant(new Tenant('demo-2', 'Demo 2'));
    }

    private function makeBackup(): Backup
    {
        return new Backup(
            triggeredByUserId: Uuid::v7(),
            triggeredByAction: BackupTriggerAction::PreImport,
        );
    }
}
