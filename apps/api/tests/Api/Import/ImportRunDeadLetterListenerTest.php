<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportSessionStatus;
use App\Import\Domain\Message\ImportRunMessage;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use App\Import\Infrastructure\Messenger\ImportRunDeadLetterListener;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;

/**
 * IMP2-2.9 (#1485, ADR-0019 D11) — when an {@see ImportRunMessage} exhausts the
 * long-backoff retry policy (e.g. it never got the per-tenant bulk lock), the
 * message dead-letters and the {@see ImportSession} would otherwise sit
 * `pending`/`running` forever. The listener flips it to `failed` with a re-run
 * hint — but only on the FINAL failure and only for a still-active session.
 */
final class ImportRunDeadLetterListenerTest extends CatalogApiTestCase
{
    #[Test]
    public function exhaustedRetryFailsTheRunningSession(): void
    {
        [$session, $message] = $this->seedRunningSession();

        $this->dispatchFailure($message, willRetry: false);

        $reloaded = $this->reload($session->getId());
        self::assertSame(
            ImportSessionStatus::Failed,
            $reloaded->getStatus(),
            'a dead-lettered import run must flip its session to failed',
        );
        self::assertStringContainsString('blokady operacji masowych', (string) $reloaded->getErrorMessage());
    }

    #[Test]
    public function retriableFailureLeavesSessionUntouched(): void
    {
        [$session, $message] = $this->seedRunningSession();

        // willRetry === true → the backoff policy still has attempts left; the
        // listener must NOT pre-emptively fail the session.
        $this->dispatchFailure($message, willRetry: true);

        $reloaded = $this->reload($session->getId());
        self::assertSame(
            ImportSessionStatus::Running,
            $reloaded->getStatus(),
            'a retriable failure must leave the session running',
        );
    }

    #[Test]
    public function terminalSessionIsNotClobbered(): void
    {
        [$session, $message] = $this->seedRunningSession();
        // The handler already recorded a terminal outcome before the failure
        // event unwinds — the listener must not overwrite it.
        $session->markCompleted();
        $this->sessions()->save($session);

        $this->dispatchFailure($message, willRetry: false);

        $reloaded = $this->reload($session->getId());
        self::assertSame(
            ImportSessionStatus::Success,
            $reloaded->getStatus(),
            'a session that already reached a terminal status must not be clobbered',
        );
    }

    /**
     * @return array{ImportSession, ImportRunMessage}
     */
    private function seedRunningSession(): array
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $productOt = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert($productOt instanceof ObjectType);

        $session = new ImportSession(
            userId: Uuid::v7(),
            targetObjectType: $productOt,
            fileName: 'dead-letter.csv',
            fileSizeBytes: 64,
        );
        $session->assignTenant($tenant);
        $session->markRunning();
        $em->persist($session);
        $em->flush();

        return [$session, new ImportRunMessage($session->getId(), $tenant->getId())];
    }

    private function dispatchFailure(ImportRunMessage $message, bool $willRetry): void
    {
        $event = new WorkerMessageFailedEvent(
            new Envelope($message),
            'import',
            new RuntimeException('bulk lock never acquired'),
        );
        if ($willRetry) {
            $event->setForRetry();
        }

        $listener = self::getContainer()->get(ImportRunDeadLetterListener::class);
        \assert($listener instanceof ImportRunDeadLetterListener);
        $listener($event);
    }

    private function reload(Uuid $id): ImportSession
    {
        $this->em()->clear();
        $session = $this->sessions()->findById($id);
        \assert($session instanceof ImportSession);

        return $session;
    }

    private function sessions(): ImportSessionRepositoryInterface
    {
        $repo = self::getContainer()->get(ImportSessionRepositoryInterface::class);
        \assert($repo instanceof ImportSessionRepositoryInterface);

        return $repo;
    }
}
