<?php

declare(strict_types=1);

namespace App\Tests\Unit\Import;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportSessionStatus;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ImportSessionTest extends TestCase
{
    #[Test]
    public function constructorDefaultsAreSane(): void
    {
        $session = $this->makeSession();

        self::assertInstanceOf(Uuid::class, $session->getId());
        self::assertSame(ImportSessionStatus::Pending, $session->getStatus());
        self::assertSame(0, $session->getSuccessCount());
        self::assertSame(0, $session->getErrorCount());
        self::assertSame(0, $session->getImagesDownloaded());
        self::assertSame(0, $session->getImagesFailed());
        self::assertNull($session->getStartedAt());
        self::assertNull($session->getCompletedAt());
        self::assertNull($session->getRollbackUntil());
    }

    #[Test]
    public function pendingTransitionsToRunningStampsStartedAt(): void
    {
        $session = $this->makeSession();
        $session->markRunning();

        self::assertSame(ImportSessionStatus::Running, $session->getStatus());
        self::assertNotNull($session->getStartedAt());
    }

    #[Test]
    public function pausedRunningRoundTrip(): void
    {
        $session = $this->makeSession();
        $session->markRunning();
        $session->markPaused();
        self::assertSame(ImportSessionStatus::Paused, $session->getStatus());

        $session->markRunning();
        self::assertSame(ImportSessionStatus::Running, $session->getStatus());
    }

    #[Test]
    public function completedZeroErrorsBecomesSuccess(): void
    {
        $session = $this->makeSession();
        $session->markRunning();
        $session->incrementSuccess();
        $session->markCompleted();

        self::assertSame(ImportSessionStatus::Success, $session->getStatus());
        self::assertNotNull($session->getCompletedAt());
        self::assertNotNull($session->getRollbackUntil());
        self::assertTrue($session->isWithinRollbackWindow());
    }

    #[Test]
    public function completedWithErrorsBecomesPartial(): void
    {
        $session = $this->makeSession();
        $session->markRunning();
        $session->incrementSuccess();
        $session->incrementError();
        $session->markCompleted();

        self::assertSame(ImportSessionStatus::Partial, $session->getStatus());
    }

    #[Test]
    public function rollbackWindowExpiresAfterTwentyFourHours(): void
    {
        $session = $this->makeSession();
        $session->markRunning();
        $session->markCompleted();

        $past = new DateTimeImmutable()->modify('+25 hours');
        self::assertFalse($session->isWithinRollbackWindow($past));
    }

    #[Test]
    public function rolledBackRequiresOpenWindow(): void
    {
        $session = $this->makeSession();
        $session->markRunning();
        $session->markCompleted();
        $session->markRolledBack();

        self::assertSame(ImportSessionStatus::RolledBack, $session->getStatus());
        self::assertNotNull($session->getRolledBackAt());
    }

    #[Test]
    public function rollbackOutsideWindowThrows(): void
    {
        $session = $this->makeSession();
        $session->markRunning();
        $session->markCompleted(rollbackWindowHours: -1);  // already expired

        $this->expectException(LogicException::class);
        $session->markRolledBack();
    }

    #[Test]
    public function rollbackFromFailedIsRejected(): void
    {
        $session = $this->makeSession();
        $session->markRunning();
        $session->markFailed('corrupted file');

        $this->expectException(LogicException::class);
        $session->markRolledBack();
    }

    #[Test]
    public function tenantCannotBeReassigned(): void
    {
        $session = $this->makeSession();
        $session->assignTenant(new Tenant('demo-1', 'Demo 1'));

        $this->expectException(LogicException::class);
        $session->assignTenant(new Tenant('demo-2', 'Demo 2'));
    }

    #[Test]
    public function invalidTransitionThrows(): void
    {
        $session = $this->makeSession();
        $session->markRunning();
        $session->markCompleted();

        $this->expectException(LogicException::class);
        $session->markRunning();
    }

    private function makeSession(): ImportSession
    {
        $type = new ObjectType('product', ObjectKind::Product, ['pl' => 'Produkt']);

        return new ImportSession(
            userId: Uuid::v7(),
            targetObjectType: $type,
            fileName: 'festo-q2-2026.xlsx',
            fileSizeBytes: 2_400_000,
        );
    }
}
