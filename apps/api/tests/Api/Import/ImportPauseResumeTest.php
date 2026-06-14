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
use App\Import\Application\Service\StagedFileService;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportMode;
use App\Import\Domain\Enum\ImportSessionStatus;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * IMP2-2.3 (#1479) — real pause / resume / cancel + crash-resilient checkpoint.
 *
 * Covers the state endpoints (transitions + conflicts + owner-scoping), the
 * resume re-dispatch (the worker actually picks the session back up), and the
 * checkpoint-skip: a resumed run skips writes at/below the checkpoint, keeps
 * the persisted counters, and reaches success without duplicating rows.
 */
final class ImportPauseResumeTest extends CatalogApiTestCase
{
    #[Test]
    public function pauseFlipsRunningToPaused(): void
    {
        $session = $this->persistRunningSession();

        $client = $this->authenticatedClient();
        $client->request('POST', \sprintf('/api/import-sessions/%s/pause', $session->getId()->toRfc4122()));

        self::assertResponseIsSuccessful();
        self::assertSame('paused', $this->decode($client)['status']);
    }

    #[Test]
    public function cancelStopsTheSession(): void
    {
        $session = $this->persistRunningSession();

        $client = $this->authenticatedClient();
        $client->request('POST', \sprintf('/api/import-sessions/%s/cancel', $session->getId()->toRfc4122()));

        self::assertResponseIsSuccessful();
        self::assertSame('cancelled', $this->decode($client)['status']);
    }

    #[Test]
    public function pauseFromNonRunningConflicts(): void
    {
        $session = $this->persistRunningSession();
        // Drive it terminal first so pause has no legal transition.
        $client = $this->authenticatedClient();
        $client->request('POST', \sprintf('/api/import-sessions/%s/cancel', $session->getId()->toRfc4122()));
        self::assertResponseIsSuccessful();

        $client->request('POST', \sprintf('/api/import-sessions/%s/pause', $session->getId()->toRfc4122()));
        self::assertResponseStatusCodeSame(409);
    }

    #[Test]
    public function crossUserPauseYields404(): void
    {
        // Session owned by a DIFFERENT user in the same tenant.
        $session = $this->persistRunningSession(Uuid::v7());

        $client = $this->authenticatedClient();
        $client->request('POST', \sprintf('/api/import-sessions/%s/pause', $session->getId()->toRfc4122()));

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function resumeReDispatchesTheWorkerToCompletion(): void
    {
        $this->seedSkuName();
        $em = $this->em();

        // A paused session whose source file is already staged at the session
        // key, checkpoint 0 → resume re-runs it from the top and completes.
        $session = $this->persistRunningSession();
        $session->markPaused();
        $em->flush();
        $this->stageSourceFor($session, "sku;name\nRES-1;A\nRES-2;B\nRES-3;C\n");

        $client = $this->authenticatedClient();
        $client->request('POST', \sprintf('/api/import-sessions/%s/resume', $session->getId()->toRfc4122()));
        self::assertResponseIsSuccessful();

        // The dev/test import transport is sync:// — the re-dispatched message
        // ran inline, so the session is finished by the time we reload it.
        $em->clear();
        $reloaded = $em->find(ImportSession::class, $session->getId()->toRfc4122());
        self::assertInstanceOf(ImportSession::class, $reloaded);
        self::assertSame(ImportSessionStatus::Success, $reloaded->getStatus(), 'resume must re-run the import to completion');
        self::assertSame(3, $this->countObjects('RES-%'));
    }

    #[Test]
    public function resumeSkipsCheckpointedRowsAndContinuesCounters(): void
    {
        $this->seedSkuName();
        $em = $this->em();

        // Simulate a session paused after 3 of 6 rows: counters + checkpoint
        // persisted, none of SK-1..6 written yet for THIS run. The resumed run
        // must skip rows ≤ 3 (no writes) and create only rows 4..6, leaving
        // success_count == total rows and zero duplicates.
        $session = $this->persistRunningSession();
        $session->configureRun(ImportMode::Create, null);
        $session->incrementSuccess();
        $session->incrementSuccess();
        $session->incrementSuccess();
        $session->recordCheckpoint(3, 'rows');
        $em->flush();

        $this->stageSourceFor($session, "sku;name\nSK-1;A\nSK-2;B\nSK-3;C\nSK-4;D\nSK-5;E\nSK-6;F\n");

        self::getContainer()->get(ImportRunHandler::class)->run($session);

        $em->clear();
        // Only rows past the checkpoint were written.
        self::assertSame(3, $this->countObjects('SK-%'), 'rows at/below the checkpoint must not be (re)created');
        self::assertSame(0, $this->countObjects('SK-1'));
        self::assertSame(0, $this->countObjects('SK-3'));
        self::assertSame(1, $this->countObjects('SK-4'));
        self::assertSame(1, $this->countObjects('SK-6'));

        $reloaded = $em->find(ImportSession::class, $session->getId()->toRfc4122());
        self::assertInstanceOf(ImportSession::class, $reloaded);
        self::assertSame(ImportSessionStatus::Success, $reloaded->getStatus());
        // 3 carried over + 3 created on resume == the 6 file rows, no double count.
        self::assertSame(6, $reloaded->getSuccessCount());
    }

    private function persistRunningSession(?Uuid $ownerOverride = null): ImportSession
    {
        $em = $this->em();
        $tenant = $this->demoTenant();
        self::getContainer()->get(TenantContext::class)->set($tenant);
        $product = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert($product instanceof ObjectType);

        $session = new ImportSession(
            userId: $ownerOverride ?? $this->adminUserId(),
            targetObjectType: $product,
            fileName: 'resume.csv',
            fileSizeBytes: 64,
        );
        $session->assignTenant($tenant);
        $session->setColumnMapping(['sku' => 'sku', 'name' => 'name']);
        $session->markRunning();
        $em->persist($session);
        $em->flush();

        return $session;
    }

    private function stageSourceFor(ImportSession $session, string $csv): void
    {
        $tenant = $this->demoTenant();
        $path = tempnam(sys_get_temp_dir(), 'pim-resume-').'.csv';
        file_put_contents($path, $csv);

        try {
            $staged = self::getContainer()->get(StagedFileService::class)->stage(
                $path,
                'resume.csv',
                (int) filesize($path),
                $tenant,
                $session->getUserId(),
            );
            $key = \sprintf(
                '%s/%s/%s',
                $tenant->getId()->toRfc4122(),
                $session->getId()->toRfc4122(),
                $session->getFileName(),
            );
            self::getContainer()->get(StagedFileService::class)->copyToKey($staged, $key);
        } finally {
            @unlink($path);
        }
    }

    private function countObjects(string $like): int
    {
        $count = $this->em()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM objects WHERE code LIKE :like',
            ['like' => $like],
        );

        return (int) (\is_scalar($count) ? $count : 0);
    }

    private function demoTenant(): Tenant
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        return $tenant;
    }

    private function adminUserId(): Uuid
    {
        $user = self::getContainer()->get(\App\Identity\Domain\Repository\UserRepositoryInterface::class)
            ->findByEmail(self::ADMIN_EMAIL);
        \assert(null !== $user);

        return $user->getId();
    }

    private function seedSkuName(): void
    {
        $em = $this->em();
        $tenant = $this->demoTenant();
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

    /**
     * @return array<mixed>
     */
    private function decode(\ApiPlatform\Symfony\Bundle\Test\Client $client): array
    {
        $decoded = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
