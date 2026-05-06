<?php

declare(strict_types=1);

namespace App\Tests\Api\Backup;

use App\Backup\Domain\Enum\BackupStatus;
use App\Backup\Domain\Repository\BackupRepositoryInterface;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use App\Tests\Support\InMemoryBackupRunner;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * IMP-06 (#447) — round-trips the manual snapshot trigger + status
 * polling endpoints. The pgBackRest CLI stays off the test box; the
 * {@see InMemoryBackupRunner} stub honours the contract so the
 * handler flow runs in-band on `sync://`.
 */
final class BackupApiTest extends CatalogApiTestCase
{
    #[Test]
    public function triggerEndpointDispatchesAndCompletesBackup(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/backups', [
            'extra' => [
                'parameters' => [],
            ],
            'body' => json_encode(['triggered_by_action' => 'manual'], JSON_THROW_ON_ERROR),
            'headers' => ['content-type' => 'application/json'],
        ]);

        self::assertResponseStatusCodeSame(202);
        $response = $client->getResponse();
        self::assertNotNull($response);
        $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertArrayHasKey('id', $body);

        $this->consumeAsyncQueue();

        $id = $body['id'] ?? null;
        self::assertIsString($id);
        $repo = self::getContainer()->get(BackupRepositoryInterface::class);
        $reload = $repo->findById(Uuid::fromString($id));
        self::assertNotNull($reload);
        self::assertSame(BackupStatus::Completed, $reload->getStatus());
        self::assertSame(12_345_678, $reload->getSizeBytes());
        self::assertSame('TEST-LABEL', $reload->getPgbackrestLabel());
    }

    #[Test]
    public function triggerEndpointSurfacesPgbackrestFailureAsBackupFailed(): void
    {
        $client = $this->authenticatedClient();

        // Flip the stub after the client boots the kernel so the
        // controller resolves the same shared instance.
        $runner = self::getContainer()->get(InMemoryBackupRunner::class);
        \assert($runner instanceof InMemoryBackupRunner);
        $runner->shouldSucceed = false;
        $runner->errorMessage = 'disk full';

        $client->request('POST', '/api/backups', [
            'body' => json_encode(['triggered_by_action' => 'pre_import'], JSON_THROW_ON_ERROR),
            'headers' => ['content-type' => 'application/json'],
        ]);

        self::assertResponseStatusCodeSame(202);
        $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);

        $this->consumeAsyncQueue();

        $id = $body['id'] ?? null;
        self::assertIsString($id);
        $repo = self::getContainer()->get(BackupRepositoryInterface::class);
        $reload = $repo->findById(Uuid::fromString($id));
        self::assertNotNull($reload);
        self::assertSame(BackupStatus::Failed, $reload->getStatus());
        self::assertSame('disk full', $reload->getErrorMessage());
    }

    #[Test]
    public function statusEndpointReturnsCurrentBackupShape(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/backups', [
            'body' => json_encode(['triggered_by_action' => 'manual'], JSON_THROW_ON_ERROR),
            'headers' => ['content-type' => 'application/json'],
        ]);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        $createdId = $created['id'] ?? null;
        self::assertIsString($createdId);

        $this->consumeAsyncQueue();

        $client->request('GET', \sprintf('/api/backups/%s', $createdId));
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('completed', $body['status']);
        self::assertSame('manual', $body['triggered_by_action']);
        self::assertNotNull($body['completed_at']);
    }

    /**
     * Drains the in-memory async transport so the snapshot handler
     * runs synchronously inside the test. Local dev sets the messenger
     * DSN to `sync://` and the dispatch is in-band; CI overrides to
     * `in-memory://` and the messages would otherwise sit in the
     * queue.
     */
    private function consumeAsyncQueue(): void
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        // sync:// transport processes inline so `get()` is a no-op there.
        if (!$transport instanceof InMemoryTransport) {
            return;
        }
        $bus = self::getContainer()->get(MessageBusInterface::class);

        foreach ($transport->get() as $envelope) {
            $bus->dispatch($envelope->with(new ReceivedStamp('async')));
            $transport->ack($envelope);
        }
    }
}
