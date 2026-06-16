<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Catalog\Domain\ObjectKind;
use App\Import\Application\Service\StagedFileService;
use App\Import\Domain\Entity\StagedFile;
use App\Import\Presentation\Command\PurgeStagedFilesCommand;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * IMP2-2.2 — staged upload: the file is sent once at parse-preview and the
 * dry-run + start steps reuse it by `staged_file_id`. Covers the happy reuse
 * path, owner-scoping (a foreign user's id is a 404), unknown ids, and the
 * 24h TTL purge command.
 */
final class StagedUploadApiTest extends CatalogApiTestCase
{
    #[Test]
    public function parsePreviewReturnsStagedFileIdReusableByDryRunAndStart(): void
    {
        $csvPath = $this->writeCsv();

        try {
            $client = $this->authenticatedClient();

            // 1. parse-preview stages the bytes once and returns the handle.
            $client->request('POST', '/api/import-sessions/parse-preview', [
                'extra' => [
                    'parameters' => [],
                    'files' => ['file' => new UploadedFile($csvPath, 'sample.csv', 'text/csv', null, true)],
                ],
            ]);
            self::assertResponseIsSuccessful();
            $preview = $this->decodeJson($client->getResponse()?->getContent());
            self::assertArrayHasKey('staged_file_id', $preview);
            $stagedFileId = $preview['staged_file_id'];
            self::assertIsString($stagedFileId);
            self::assertTrue(Uuid::isValid($stagedFileId), 'staged_file_id must be a UUID');

            // 2. dry-run reuses it — no `file` part in the request.
            $client->request('POST', '/api/import-sessions/validate-dry-run', [
                'extra' => [
                    'parameters' => [
                        'staged_file_id' => $stagedFileId,
                        'target_object_type_id' => $this->objectTypeIdFor(ObjectKind::Product),
                        'mapping' => json_encode(['sku' => 'code', 'name' => 'skip', 'price' => 'skip'], JSON_THROW_ON_ERROR),
                    ],
                ],
            ]);
            self::assertResponseIsSuccessful();
            $dryRun = $this->decodeJson($client->getResponse()?->getContent());
            self::assertSame(2, $dryRun['total_rows']);

            // 3. start reuses the same id — sync (<50 rows) returns the session.
            $client->request('POST', '/api/import-sessions', [
                'extra' => [
                    'parameters' => [
                        'staged_file_id' => $stagedFileId,
                        'target_object_type_id' => $this->objectTypeIdFor(ObjectKind::Product),
                        'mapping' => json_encode(['sku' => 'code'], JSON_THROW_ON_ERROR),
                        'mode' => 'CREATE',
                    ],
                ],
            ]);
            self::assertResponseIsSuccessful();
            $session = $this->decodeJson($client->getResponse()?->getContent());
            self::assertArrayHasKey('id', $session);
            self::assertIsString($session['id']);
            self::assertTrue(Uuid::isValid($session['id']));
        } finally {
            @unlink($csvPath);
        }
    }

    #[Test]
    public function foreignUsersStagedFileIdYields404(): void
    {
        $csvPath = $this->writeCsv();

        try {
            // Stage a file owned by a DIFFERENT user in the same tenant.
            $foreign = self::getContainer()->get(StagedFileService::class)->stage(
                $csvPath,
                'foreign.csv',
                (int) filesize($csvPath),
                $this->demoTenant(),
                Uuid::v7(),
            );

            $client = $this->authenticatedClient();
            $client->request('POST', '/api/import-sessions/validate-dry-run', [
                'extra' => [
                    'parameters' => [
                        'staged_file_id' => $foreign->getId()->toRfc4122(),
                        'target_object_type_id' => $this->objectTypeIdFor(ObjectKind::Product),
                        'mapping' => '{}',
                    ],
                ],
            ]);

            self::assertResponseStatusCodeSame(404);
        } finally {
            @unlink($csvPath);
        }
    }

    #[Test]
    public function unknownStagedFileIdYields404OnStart(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-sessions', [
            'extra' => [
                'parameters' => [
                    'staged_file_id' => Uuid::v7()->toRfc4122(),
                    'target_object_type_id' => $this->objectTypeIdFor(ObjectKind::Product),
                    'mapping' => '{}',
                    'mode' => 'CREATE',
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function purgeCommandDeletesStagedFilesOlderThanTtl(): void
    {
        $tenant = $this->demoTenant();
        self::getContainer()->get(TenantContext::class)->set($tenant);
        $repo = self::getContainer()->get(\App\Import\Domain\Repository\StagedFileRepositoryInterface::class);
        $storage = self::getContainer()->get(StagedFileService::class);

        // Stage a fresh file (survives) and persist a backdated one (purged).
        $csvPath = $this->writeCsv();
        $fresh = $storage->stage($csvPath, 'fresh.csv', (int) filesize($csvPath), $tenant, Uuid::v7());
        @unlink($csvPath);

        $oldKey = $tenant->getId()->toRfc4122().'/staged/'.Uuid::v7()->toRfc4122().'/old.csv';
        $old = new StagedFile(Uuid::v7(), 'old.csv', 12, $oldKey, null, new DateTimeImmutable('-25 hours'));
        $repo->save($old);

        $command = self::getContainer()->get(PurgeStagedFilesCommand::class);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $em = $this->em();
        $em->clear();
        self::assertNull(
            $em->getRepository(StagedFile::class)->find($old->getId()->toRfc4122()),
            'staged file older than 24h must be purged',
        );
        self::assertNotNull(
            $em->getRepository(StagedFile::class)->find($fresh->getId()->toRfc4122()),
            'staged file within the TTL must survive',
        );
    }

    #[Test]
    public function purgeCommandDeletesUndoLogForClosedRollbackWindows(): void
    {
        // IMP2-2.4 (#1480, spec §6) — the same TTL sweep that drops abandoned
        // staged uploads also purges import_undo_log of sessions whose rollback
        // window has fully closed (rollback_until in the past): the before-state
        // is dead weight once the import can no longer be rolled back. Rows for a
        // session still inside its window must survive.
        $tenant = $this->demoTenant();
        $em = $this->em();
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $type = self::getContainer()->get(\App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert($type instanceof \App\Catalog\Domain\Entity\ObjectType);

        // Two completed sessions: one whose window is still open, one expired.
        $openSession = $this->completedSession($type, 'open.csv');
        $closedSession = $this->completedSession($type, 'closed.csv');

        // Force the second session's window into the past — there is no setter,
        // so write rollback_until directly (the purge query keys on it).
        $em->getConnection()->executeStatement(
            'UPDATE import_sessions SET rollback_until = :past WHERE id = :id',
            ['past' => new DateTimeImmutable('-1 hour')->format('Y-m-d H:i:s'), 'id' => $closedSession->getId()->toRfc4122()],
        );

        $undoLog = self::getContainer()->get(\App\Import\Domain\Repository\ImportUndoLogRepositoryInterface::class);
        $openUndo = $this->undoRowFor($openSession, $tenant);
        $closedUndo = $this->undoRowFor($closedSession, $tenant);
        $undoLog->add($openUndo);
        $undoLog->add($closedUndo);
        $em->flush();
        $em->clear();

        $command = self::getContainer()->get(PurgeStagedFilesCommand::class);
        $tester = new CommandTester($command);
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();

        $undoRepo = $em->getRepository(\App\Import\Domain\Entity\ImportUndoLog::class);
        self::assertNull(
            $undoRepo->find($closedUndo->getId()->toRfc4122()),
            'undo-log of a session whose rollback window closed must be purged',
        );
        self::assertNotNull(
            $undoRepo->find($openUndo->getId()->toRfc4122()),
            'undo-log of a session still inside its rollback window must survive',
        );
    }

    private function completedSession(\App\Catalog\Domain\Entity\ObjectType $type, string $fileName): \App\Import\Domain\Entity\ImportSession
    {
        $session = new \App\Import\Domain\Entity\ImportSession(
            userId: Uuid::v7(),
            targetObjectType: $type,
            fileName: $fileName,
            fileSizeBytes: 10,
        );
        $session->assignTenant($this->demoTenant());
        $session->markRunning();
        $session->markCompleted();
        self::getContainer()->get(\App\Import\Domain\Repository\ImportSessionRepositoryInterface::class)->save($session);

        return $session;
    }

    private function undoRowFor(\App\Import\Domain\Entity\ImportSession $session, Tenant $tenant): \App\Import\Domain\Entity\ImportUndoLog
    {
        $row = new \App\Import\Domain\Entity\ImportUndoLog(
            $session,
            Uuid::v7(),
            \App\Import\Domain\Enum\ImportUndoOperation::ValueOverwritten,
            ['value' => ['value' => 'before'], 'provenance' => 'manual'],
            'name',
        );
        $row->assignTenant($tenant);

        return $row;
    }

    private function demoTenant(): Tenant
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        return $tenant;
    }

    /**
     * @return array<mixed>
     */
    private function decodeJson(?string $raw): array
    {
        self::assertNotNull($raw);
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }

    private function writeCsv(): string
    {
        $contents = "sku;name;price\nABC-001;Wkręt M6;9.99\nXYZ-002;Śruba;14.50\n";
        $path = tempnam(sys_get_temp_dir(), 'pim-staged-');
        \assert(false !== $path);
        $renamed = $path.'.csv';
        rename($path, $renamed);
        file_put_contents($renamed, $contents);

        return $renamed;
    }
}
