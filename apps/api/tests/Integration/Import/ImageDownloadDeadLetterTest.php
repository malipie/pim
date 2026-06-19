<?php

declare(strict_types=1);

namespace App\Tests\Integration\Import;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Import\Domain\Entity\ImportLog;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportSessionStatus;
use App\Import\Domain\Message\ImageDownloadJob;
use App\Import\Domain\Message\ImageDownloadMessage;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use App\Import\Infrastructure\Messenger\ImageDownloadDeadLetterListener;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * AUD-034 (W2-10) — when an {@see ImageDownloadMessage} exhausts its retries and
 * dead-letters, the session's {@see ImportSession::$pendingImageBatches} counter
 * never reaches zero on the success path, so the run would sit `running` forever
 * (the row phase finished but the gate never closes). The
 * {@see ImageDownloadDeadLetterListener} replays the SAME atomic decrement the
 * handler runs on success — and, when it drives the counter to zero after the
 * row phase, finalizes the session (as `partial`, because the lost images bump
 * `images_failed` + leave an error log so the operator knows media was dropped).
 *
 * Mirrors {@see ImportRunDeadLetterListenerTest}: fires only on the FINAL failure
 * and only for a still-active session.
 */
final class ImageDownloadDeadLetterTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;

    #[Test]
    public function lastDeadLetteredBatchFinalisesTheRunningSession(): void
    {
        // One pending batch + row phase already done: the dead-lettered batch is
        // the LAST one, so the listener must close the gate and finalise.
        [$session, $message] = $this->seedAwaitingMediaSession(pendingBatches: 1, rowPhaseComplete: true, imageRefs: 2);

        $this->dispatchFailure($message, willRetry: false);

        $reloaded = $this->reload($session->getId());
        self::assertSame(
            ImportSessionStatus::Partial,
            $reloaded->getStatus(),
            'the last dead-lettered media batch must finalise the session (partial — images were lost)',
        );
        self::assertSame(0, $reloaded->getPendingImageBatches(), 'the pending-batch gate must reach zero');
        self::assertSame(2, $reloaded->getImagesFailed(), 'every lost image of the batch must bump images_failed');

        $logs = $this->em()->getRepository(ImportLog::class)->findBy(['importSession' => $reloaded->getId()]);
        self::assertNotEmpty($logs, 'a dead-lettered media batch must leave an error log for the operator');
    }

    #[Test]
    public function earlierDeadLetteredBatchOnlyDecrementsAndLeavesSessionRunning(): void
    {
        // Two pending batches: the dead-lettered one decrements to 1 but must
        // NOT finalise — a sibling batch is still in flight.
        [$session, $message] = $this->seedAwaitingMediaSession(pendingBatches: 2, rowPhaseComplete: true, imageRefs: 1);

        $this->dispatchFailure($message, willRetry: false);

        $reloaded = $this->reload($session->getId());
        self::assertSame(
            ImportSessionStatus::Running,
            $reloaded->getStatus(),
            'a non-final dead-lettered batch must leave the session running',
        );
        self::assertSame(1, $reloaded->getPendingImageBatches(), 'the gate must decrement by exactly one');
        self::assertSame(1, $reloaded->getImagesFailed());
    }

    #[Test]
    public function retriableFailureLeavesSessionUntouched(): void
    {
        [$session, $message] = $this->seedAwaitingMediaSession(pendingBatches: 1, rowPhaseComplete: true, imageRefs: 1);

        // willRetry === true → the backoff policy still has attempts left; the
        // listener must NOT decrement or finalise.
        $this->dispatchFailure($message, willRetry: true);

        $reloaded = $this->reload($session->getId());
        self::assertSame(ImportSessionStatus::Running, $reloaded->getStatus());
        self::assertSame(1, $reloaded->getPendingImageBatches(), 'a retriable failure must not touch the gate');
        self::assertSame(0, $reloaded->getImagesFailed());
    }

    #[Test]
    public function terminalSessionIsNotClobbered(): void
    {
        [$session, $message] = $this->seedAwaitingMediaSession(pendingBatches: 1, rowPhaseComplete: true, imageRefs: 1);
        // The handler already finalised before the failure event unwinds. Reload
        // through the repo first — the seeder cleared the EM, so the original
        // instance is detached and saving it would re-cascade its associations.
        $managed = $this->reload($session->getId());
        $managed->markCompleted();
        $this->sessions()->save($managed);

        $this->dispatchFailure($message, willRetry: false);

        $reloaded = $this->reload($session->getId());
        self::assertSame(
            ImportSessionStatus::Success,
            $reloaded->getStatus(),
            'a session that already reached a terminal status must not be clobbered',
        );
    }

    /**
     * @return array{ImportSession, ImageDownloadMessage}
     */
    private function seedAwaitingMediaSession(int $pendingBatches, bool $rowPhaseComplete, int $imageRefs): array
    {
        $tenant = $this->createTenant('demo');
        $em = $this->em();
        $this->tenantContext()->set($tenant);
        $type = $this->productObjectType($em);

        $session = new ImportSession(
            userId: Uuid::v7(),
            targetObjectType: $type,
            fileName: 'media.csv',
            fileSizeBytes: 128,
        );
        $session->assignTenant($tenant);
        $session->markRunning();
        for ($i = 0; $i < $pendingBatches; ++$i) {
            $session->incrementPendingImageBatches();
        }
        if ($rowPhaseComplete) {
            $session->markRowPhaseComplete();
        }
        $em->persist($session);
        $em->flush();
        $em->clear();

        $urls = [];
        for ($i = 0; $i < $imageRefs; ++$i) {
            $urls[] = \sprintf('https://example.com/img-%d.jpg', $i);
        }
        $job = new ImageDownloadJob(
            objectId: Uuid::v7()->toRfc4122(),
            attributeCode: 'images',
            locale: null,
            channelId: null,
            existingUuids: [],
            urls: $urls,
            rowNumber: 1,
            sku: 'SKU-1',
        );

        return [$session, new ImageDownloadMessage($session->getId(), $tenant->getId(), [$job])];
    }

    private function dispatchFailure(ImageDownloadMessage $message, bool $willRetry): void
    {
        $event = new WorkerMessageFailedEvent(
            new Envelope($message),
            'import',
            new RuntimeException('image download failed after retries'),
        );
        if ($willRetry) {
            $event->setForRetry();
        }

        $listener = self::getContainer()->get(ImageDownloadDeadLetterListener::class);
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
        return self::getContainer()->get(ImportSessionRepositoryInterface::class);
    }

    private function productObjectType(EntityManagerInterface $em): ObjectType
    {
        $type = $em->getRepository(ObjectType::class)->findOneBy(['kind' => ObjectKind::Product]);
        if ($type instanceof ObjectType) {
            return $type;
        }

        $type = new ObjectType('product', ObjectKind::Product, ['en' => 'Product']);
        $em->persist($type);
        $em->flush();

        return $type;
    }

    private function tenantContext(): TenantContext
    {
        return self::getContainer()->get(TenantContext::class);
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function createTenant(string $code): Tenant
    {
        $tenant = new Tenant($code, ucfirst($code).' Tenant');
        $em = $this->em();
        $em->persist($tenant);
        $em->flush();

        return $tenant;
    }
}
