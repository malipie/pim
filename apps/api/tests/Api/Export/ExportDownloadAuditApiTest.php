<?php

declare(strict_types=1);

namespace App\Tests\Api\Export;

use App\Export\Domain\Entity\ExportSession;
use App\Export\Domain\Enum\ExportEntityType;
use App\Export\Domain\Enum\ExportFormat;
use App\Export\Domain\Enum\ExportSource;
use App\Export\Domain\Enum\ExportTargetScope;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * AUD-052 (W2-11) — a data export leaves a dedicated `data_export` audit trail.
 *
 * The generic AuditLogListener only records HTTP metadata (method + permission
 * outcome, old/new null) — it never tells you that PII left the building. This
 * test pins a dedicated audit entry written at download time with
 * action=`data_export`, the session id as resource id, and the export scope in
 * `new_value` (entity type / format / row count) — the compliance question
 * "who exported what, when?" must be answerable from the audit log.
 *
 * FAILING-FIRST: before the fix, downloading an export writes no `data_export`
 * row, so the COUNT below is 0 and the test is RED.
 */
final class ExportDownloadAuditApiTest extends CatalogApiTestCase
{
    #[Test]
    public function downloadingExportWritesDataExportAuditEntry(): void
    {
        $session = $this->doneSession();
        $this->em()->persist($session);
        $this->em()->flush();

        $client = $this->authenticatedClient();
        $response = $client->request(
            'GET',
            sprintf('/api/exports/sessions/%s/download', $session->getId()->toRfc4122()),
        );

        self::assertSame(200, $response->getStatusCode());

        $count = $this->em()->getConnection()->fetchOne(
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'data_export' AND resource_id = :rid",
            ['rid' => $session->getId()->toRfc4122()],
        );
        self::assertSame(1, \is_numeric($count) ? (int) $count : 0, 'A data_export audit entry must be written on download.');

        /** @var array<string, mixed>|false $row */
        $row = $this->em()->getConnection()->fetchAssociative(
            "SELECT new_value, resource_type FROM audit_logs WHERE action = 'data_export' AND resource_id = :rid",
            ['rid' => $session->getId()->toRfc4122()],
        );
        self::assertIsArray($row);
        self::assertIsString($row['new_value']);
        /** @var array<string, mixed> $newValue */
        $newValue = json_decode($row['new_value'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('product', $newValue['entity_type'] ?? null);
        self::assertSame('csv', $newValue['format'] ?? null);
    }

    private function doneSession(): ExportSession
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $user = self::getContainer()->get(UserRepositoryInterface::class)->findByEmail(self::ADMIN_EMAIL);
        \assert(null !== $user);

        $session = new ExportSession(
            userId: $user->getId(),
            source: ExportSource::CentralTab,
            format: ExportFormat::Csv,
            targetScope: ExportTargetScope::All,
            selectedColumns: ['sku'],
            entityType: ExportEntityType::Product,
        );
        $session->assignTenant($tenant);

        $path = sprintf('%s/%s.csv', $tenant->getId()->toRfc4122(), $session->getId()->toRfc4122());
        $session->markRunning();
        $session->markDone(1, $path, 9);

        // The container resolves `exports.storage` to a concrete Filesystem
        // (a FilesystemOperator) — write the export bytes the download streams.
        self::getContainer()->get('exports.storage')->write($path, 'sku\nABC1');

        return $session;
    }
}
