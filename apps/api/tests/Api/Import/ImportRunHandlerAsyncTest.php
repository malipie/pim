<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Import\Application\Handler\ImportRunHandler;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportSessionStatus;
use App\Import\Domain\Message\ImportRunMessage;
use App\Shared\Application\BulkOperationLock;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * IMP2-1.10 (#1473) — first tests for the async import path. The dev/test
 * MESSENGER_IMPORT_TRANSPORT_DSN is `sync://`, so a routed ImportRunMessage
 * still executes inline; these tests assert the ROUTING (the message targets
 * the dedicated `import` transport), the end-to-end handler outcome for a
 * file over the 50-row sync threshold, re-delivery idempotency, and the
 * recoverable-retry contract on the per-tenant bulk lock.
 */
final class ImportRunHandlerAsyncTest extends CatalogApiTestCase
{
    private const int LARGE = 60; // > StartImportController::SYNC_THRESHOLD_ROWS

    #[Test]
    public function importRunMessageRoutesToDedicatedTransport(): void
    {
        // Audit MEDIUM-007 posture: the message must target an explicit
        // transport (the dedicated `import` queue), never implicit default.
        $locator = self::getContainer()->get('messenger.senders_locator');
        self::assertInstanceOf(SendersLocatorInterface::class, $locator);

        $message = new ReflectionClass(ImportRunMessage::class)->newInstanceWithoutConstructor();
        $aliases = [];
        foreach ($locator->getSenders(new Envelope($message)) as $alias => $_sender) {
            $aliases[] = $alias;
        }

        self::assertContains('import', $aliases, 'ImportRunMessage must route to the dedicated import transport.');
    }

    #[Test]
    public function largeFileImportsEndToEndViaAsyncPath(): void
    {
        // > 50 data rows → the controller routes through ImportRunMessage
        // (the xlsx/large path); the sync transport completes it inline.
        $this->seedSkuName();
        $em = $this->em();

        $rows = ['sku;name'];
        for ($i = 1; $i <= self::LARGE; ++$i) {
            $rows[] = \sprintf('ASYNC-%d;Product %d', $i, $i);
        }
        $sessionId = $this->upload(implode("\n", $rows)."\n");

        $em->clear();
        $session = $em->find(ImportSession::class, Uuid::fromString($sessionId));
        \assert($session instanceof ImportSession);
        self::assertSame(ImportSessionStatus::Success, $session->getStatus(), 'async-routed import completes');
        self::assertSame(self::LARGE, $session->getSuccessCount());
        self::assertSame(self::LARGE, $session->getTotalRows());

        $persisted = $em->getConnection()->fetchOne("SELECT COUNT(*) FROM objects WHERE code LIKE 'ASYNC-%'");
        self::assertSame(self::LARGE, (int) (\is_scalar($persisted) ? $persisted : 0));
    }

    #[Test]
    public function reDeliveryIsIdempotentUnderUpsert(): void
    {
        // A re-delivered import (UPSERT) must not duplicate objects — the
        // resilience contract the worker relies on after a crash/redeliver
        // (full offset checkpoint is IMP2-2.3; UPSERT idempotency holds now).
        $this->seedSkuName();
        $em = $this->em();

        $rows = ['sku;name'];
        for ($i = 1; $i <= self::LARGE; ++$i) {
            $rows[] = \sprintf('IDEM-%d;Product %d', $i, $i);
        }
        $csv = implode("\n", $rows)."\n";

        $this->upload($csv);
        $secondId = $this->upload($csv);

        $em->clear();
        $count = $em->getConnection()->fetchOne("SELECT COUNT(*) FROM objects WHERE code LIKE 'IDEM-%'");
        self::assertSame(self::LARGE, (int) (\is_scalar($count) ? $count : 0), 're-delivery must not duplicate objects');

        $second = $em->find(ImportSession::class, Uuid::fromString($secondId));
        \assert($second instanceof ImportSession);
        // IMP2-2.6 — the second (identical) run no longer re-writes every row:
        // the compare-values diff detects unchanged value+provenance and skips
        // the UPDATE, so re-delivery is not just object-idempotent but write-free.
        self::assertSame(0, $second->getUpdatedCount(), 'second run rewrites nothing');
        self::assertSame(self::LARGE, $second->getSkippedCount(), 'second run skips every unchanged row');
    }

    #[Test]
    public function bulkLockCollisionThrowsRecoverable(): void
    {
        // PROD-05 — a second concurrent import for the same tenant must surface
        // as a recoverable Messenger failure (retry with backoff), never a
        // dead-letter and never a corrupted session.
        $this->seedSkuName();
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
            fileName: 'locked.csv',
            fileSizeBytes: 64,
        );
        $session->assignTenant($tenant);
        $session->setColumnMapping(['sku' => 'sku', 'name' => 'name']);
        $em->persist($session);
        $em->flush();
        $sessionId = $session->getId();

        // Hold the per-tenant bulk lock so the handler cannot acquire it.
        $lock = self::getContainer()->get(BulkOperationLock::class)->acquire($tenant);
        self::assertNotNull($lock, 'precondition: the test holds the bulk lock');

        try {
            $handler = self::getContainer()->get(ImportRunHandler::class);
            $this->expectException(RecoverableMessageHandlingException::class);
            $handler(new ImportRunMessage($sessionId, $tenant->getId()));
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function bulkImportRebuildsAttributesIndexedViaAsyncPath(): void
    {
        // IMP2-2.6 — the row phase runs under BulkContext, so the per-flush sync
        // rebuild is suppressed; the end-of-run ObjectValuesChangedMessage (sync
        // transport in test) must rebuild attributes_indexed inline instead.
        // Prove every imported object ends up with a populated cache — i.e. the
        // async path actually replaced the suppressed sync rebuild (the older
        // count/object assertions would not have caught an empty cache).
        $this->seedSkuName();
        $em = $this->em();

        $rows = ['sku;name'];
        for ($i = 1; $i <= self::LARGE; ++$i) {
            $rows[] = \sprintf('IDX-%d;Product %d', $i, $i);
        }
        $this->upload(implode("\n", $rows)."\n");
        // The end-of-run rebuild is dispatched to the `async` transport. Under CI
        // (`in-memory://`) it sits buffered; replay it so the cache is rebuilt.
        $this->consumeAsyncQueue();

        $em->clear();
        $indexed = $em->getConnection()->fetchOne("SELECT attributes_indexed FROM objects WHERE code = 'IDX-1'");
        self::assertIsString($indexed);
        $decoded = json_decode($indexed, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('sku', $decoded, 'attributes_indexed rebuilt by the async handler');
        self::assertArrayHasKey('name', $decoded);

        $emptyCount = $em->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM objects WHERE code LIKE 'IDX-%' AND attributes_indexed = '{}'::jsonb",
        );
        self::assertSame(0, (int) (\is_scalar($emptyCount) ? $emptyCount : 1), 'no imported object left with an empty attributes_indexed cache');
    }

    /**
     * Drain the in-memory `async` transport so the end-of-run attributes_indexed
     * rebuild runs inside the test. Local dev uses `sync://` (the dispatch is
     * in-band, so this is a no-op); CI overrides `async` to `in-memory://`, where
     * the ObjectValuesChangedMessage would otherwise sit unhandled. Re-dispatching
     * with a {@see ReceivedStamp} replays it through the full middleware stack
     * (tenant rebind + RLS GUC from the message's TenantStamp) before the handler.
     */
    private function consumeAsyncQueue(): void
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        if (!$transport instanceof InMemoryTransport) {
            return;
        }
        $bus = self::getContainer()->get(MessageBusInterface::class);
        foreach ($transport->get() as $envelope) {
            $bus->dispatch($envelope->with(new ReceivedStamp('async')));
            $transport->ack($envelope);
        }
    }

    private function upload(string $csv): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pim-async-').'.csv';
        file_put_contents($path, $csv);

        try {
            $client = $this->authenticatedClient();
            $client->request('POST', '/api/import-sessions', [
                'extra' => [
                    'parameters' => [
                        'target_object_type_id' => $this->objectTypeIdFor(ObjectKind::Product),
                        'mapping' => json_encode(['sku' => 'sku', 'name' => 'name'], JSON_THROW_ON_ERROR),
                        'mode' => 'UPSERT',
                    ],
                    'files' => ['file' => new UploadedFile($path, 'async.csv', 'text/csv', null, true)],
                ],
            ]);
            self::assertResponseIsSuccessful();
            $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($body);
            $id = $body['id'];
            \assert(\is_string($id));

            return $id;
        } finally {
            @unlink($path);
        }
    }

    private function seedSkuName(): void
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);
        $product = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert($product instanceof ObjectType);

        $sku = new Attribute('sku', ['en' => 'SKU'], AttributeType::Text);
        $name = new Attribute('name', ['en' => 'Name'], AttributeType::Text);
        $em->persist($sku);
        $em->persist($name);
        $em->persist(new ObjectTypeAttribute($product, $sku, false, 1));
        $em->persist(new ObjectTypeAttribute($product, $name, false, 2));
        $em->flush();
    }
}
