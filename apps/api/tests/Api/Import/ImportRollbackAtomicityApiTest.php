<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Application\AttributesIndexedRebuilder;
use App\Catalog\Application\Reindex\BulkReindexQueueInterface;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use App\Import\Application\Service\ImportRollbackService;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use App\Import\Domain\Repository\ImportUndoLogRepositoryInterface;
use App\Shared\Application\BulkOperationLock;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * W2-5 / AUD-040 — rollback must be ALL-OR-NOTHING. The v2 rollback ran
 * replayUndoLog → DELETE values → DELETE objects → markRolledBack as five
 * independent commits; a crash between any two left the catalog half-rolled-back
 * (orphan objects, or data deleted while the session still read `success` →
 * a second rollback replayed a spent undo-log).
 *
 * This pins the contract: when the rollback throws AFTER the DELETEs would have
 * run, the whole DB mutation reverts — the created objects + their values are
 * still present and the session status is unchanged — so the operator can retry
 * cleanly. The retry (with the real collaborators) then rolls back exactly once.
 */
final class ImportRollbackAtomicityApiTest extends CatalogApiTestCase
{
    #[Test]
    public function crashDuringRollbackLeavesNothingDeletedAndStatusIntact(): void
    {
        $this->seedSkuName();

        // Import #1 seeds ATM-1..2 with the "old" names.
        $this->import("sku;name\nATM-1;Old1\nATM-2;Old2\n");
        // Import #2 overwrites those names and creates ATM-3..4.
        $sessionId = $this->import("sku;name\nATM-1;New1\nATM-2;New2\nATM-3;New3\nATM-4;New4\n");

        self::assertSame('New1', $this->nameOf('ATM-1'), 'precondition: import #2 overwrote the name');
        self::assertSame(4, $this->countObjects('ATM-%'), 'precondition: 4 objects exist (2 pre-existing + 2 created)');

        $session = $this->reloadSession($sessionId);

        // Inject a crash at the LAST DB write of the rollback: the session
        // save() that flips the status — i.e. AFTER replayUndoLog flushed the
        // restored values and AFTER the created objects/values were DELETEd
        // (the audit's "before E" window: data gone, status still `success`).
        // A non-atomic rollback has already committed those deletes when this
        // fires; an atomic rollback reverts the whole lot.
        $service = $this->rollbackServiceWith(new ThrowOnSaveSessionRepository(
            self::getContainer()->get(ImportSessionRepositoryInterface::class),
        ));

        $threw = false;
        try {
            $service->rollback($session);
        } catch (RuntimeException $e) {
            $threw = true;
            self::assertSame('boom: crash after DELETEs, before status commit', $e->getMessage());
        }
        self::assertTrue($threw, 'precondition: the injected crash actually fired');

        // ── Atomicity contract ──────────────────────────────────────────────
        // The created objects and their values survive (nothing deleted)...
        $this->em()->clear();
        self::assertSame(4, $this->countObjects('ATM-%'), 'created objects must NOT be deleted by a crashed rollback');
        self::assertSame('New3', $this->nameOf('ATM-3'), 'created value must survive');
        // ...and the overwritten value is NOT half-restored either.
        self::assertSame('New1', $this->nameOf('ATM-1'), 'overwrite must NOT be half-restored by a crashed rollback');
        // ...and the session is still rollbackable (status untouched) so a
        // retry is legal and operates on an intact undo-log (no double-apply).
        $afterCrash = $this->reloadSession($sessionId);
        self::assertSame('success', $afterCrash->getStatus()->value, 'status must be untouched after a crashed rollback');
        self::assertTrue($afterCrash->getStatus()->isRollbackable(), 'a crashed rollback must remain retryable');

        // ── Clean retry with the real collaborators rolls back exactly once ──
        $client = $this->authenticatedClient();
        $client->request('POST', \sprintf('/api/import-sessions/%s/rollback', $sessionId));
        self::assertResponseIsSuccessful();
        $body = $this->decode($client);

        self::assertSame('rolled_back', $body['status']);
        self::assertSame(2, $body['deleted_objects'], 'retry deletes ATM-3 + ATM-4 once');
        self::assertSame(4, $body['restored_values'], 'retry restores ATM-1/ATM-2 sku+name once');
        self::assertSame(0, $body['skipped_manual_edits']);

        $this->em()->clear();
        self::assertSame('Old1', $this->nameOf('ATM-1'), 'retry restored the pre-import value');
        self::assertSame(2, $this->countObjects('ATM-%'), 'retry removed the created objects');
    }

    private function rollbackServiceWith(ImportSessionRepositoryInterface $sessions): ImportRollbackService
    {
        $c = self::getContainer();

        return new ImportRollbackService(
            $this->em(),
            $c->get(Connection::class),
            $sessions,
            $c->get(ImportUndoLogRepositoryInterface::class),
            $c->get(ObjectValueRepositoryInterface::class),
            $c->get(AttributesIndexedRebuilder::class),
            $c->get(BulkReindexQueueInterface::class),
            $c->get(BulkOperationLock::class),
            $c->get(TenantContext::class),
        );
    }

    private function reloadSession(string $sessionId): \App\Import\Domain\Entity\ImportSession
    {
        $session = self::getContainer()->get(ImportSessionRepositoryInterface::class)
            ->findById(Uuid::fromString($sessionId));
        \assert($session instanceof \App\Import\Domain\Entity\ImportSession);

        return $session;
    }

    private function import(string $csv): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pim-atm-').'.csv';
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
                    'files' => ['file' => new UploadedFile($path, 'atm.csv', 'text/csv', null, true)],
                ],
            ]);
            self::assertResponseIsSuccessful();

            $id = $this->decode($client)['id'] ?? null;

            return \is_scalar($id) ? (string) $id : '';
        } finally {
            @unlink($path);
        }
    }

    private function nameOf(string $code): ?string
    {
        $value = $this->em()->getConnection()->fetchOne(
            <<<'SQL'
                SELECT ov.value->>'value'
                FROM object_values ov
                JOIN objects o ON o.id = ov.object_id
                JOIN attributes a ON a.id = ov.attribute_id
                WHERE o.code = :code AND a.code = 'name'
                LIMIT 1
                SQL,
            ['code' => $code],
        );

        return \is_scalar($value) && false !== $value ? (string) $value : null;
    }

    private function countObjects(string $like): int
    {
        $count = $this->em()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM objects WHERE code LIKE :like',
            ['like' => $like],
        );

        return (int) (\is_scalar($count) ? $count : 0);
    }

    /**
     * @return array<mixed>
     */
    private function decode(\ApiPlatform\Symfony\Bundle\Test\Client $client): array
    {
        $decoded = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
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

/**
 * Session-repository decorator that delegates findById() (so the in-transaction
 * reload still works) but throws on save() — the rollback's final DB write, the
 * status flip. Models a worker crash AFTER the created objects/values were
 * deleted but BEFORE the status commit. An atomic rollback must revert the
 * deletes when this fires.
 */
final class ThrowOnSaveSessionRepository implements ImportSessionRepositoryInterface
{
    public function __construct(private ImportSessionRepositoryInterface $inner)
    {
    }

    public function save(\App\Import\Domain\Entity\ImportSession $session): void
    {
        throw new RuntimeException('boom: crash after DELETEs, before status commit');
    }

    public function findById(Uuid $id): ?\App\Import\Domain\Entity\ImportSession
    {
        return $this->inner->findById($id);
    }

    public function findByTenantAndUser(Tenant $tenant, Uuid $userId, int $limit = 50): array
    {
        return $this->inner->findByTenantAndUser($tenant, $userId, $limit);
    }
}
