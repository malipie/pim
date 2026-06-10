<?php

declare(strict_types=1);

namespace App\Tests\Api\Export;

use App\Export\Domain\Entity\ExportSession;
use App\Export\Domain\Enum\ExportEntityType;
use App\Export\Domain\Enum\ExportFormat;
use App\Export\Domain\Enum\ExportSource;
use App\Export\Domain\Enum\ExportStatus;
use App\Export\Domain\Enum\ExportTargetScope;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Tenant;
use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * EXR-15 (#1391) — POST /api/exports/sessions/{id}/cancel.
 *
 * The endpoint persists the terminal `cancelled` status; the async
 * handler polls it between chunks and stops gracefully. Contract under
 * test: pending/running → 200 + cancelled, terminal states → 409.
 * The mid-run graceful stop itself needs a real queue worker (dev runs
 * the sync transport inline), so it is exercised by the chunk-callback
 * logic in {@see \App\Export\Application\Async\ExportJobHandler} and
 * verified manually on a worker-backed stack.
 */
final class ExportCancelApiTest extends CatalogApiTestCase
{
    #[Test]
    public function cancelsPendingSessionAndRejectsSecondCancel(): void
    {
        $session = $this->session();
        $this->em()->persist($session);
        $this->em()->flush();

        $client = $this->authenticatedClient();
        $response = $client->request(
            'POST',
            sprintf('/api/exports/sessions/%s/cancel', $session->getId()->toRfc4122()),
        );

        self::assertSame(200, $response->getStatusCode());
        /** @var array<string, mixed> $body */
        $body = $response->toArray(false);
        self::assertSame('cancelled', $body['status']);

        // Second cancel hits a terminal state → 409.
        $conflict = $client->request(
            'POST',
            sprintf('/api/exports/sessions/%s/cancel', $session->getId()->toRfc4122()),
        );
        self::assertSame(409, $conflict->getStatusCode());
    }

    #[Test]
    public function rejectsCancellingCompletedSession(): void
    {
        $session = $this->session();
        $session->markRunning();
        $session->markDone(5, 'tenant/file.csv', 123);
        $this->em()->persist($session);
        $this->em()->flush();

        $client = $this->authenticatedClient();
        $response = $client->request(
            'POST',
            sprintf('/api/exports/sessions/%s/cancel', $session->getId()->toRfc4122()),
        );

        self::assertSame(409, $response->getStatusCode());

        $row = $this->em()->getConnection()->fetchOne(
            'SELECT status FROM export_sessions WHERE id = :id',
            ['id' => $session->getId()->toRfc4122()],
        );
        self::assertSame(ExportStatus::Done->value, $row);
    }

    private function session(): ExportSession
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
            encoding: null,
            filterSnapshot: null,
            selectedObjectIds: null,
            locales: null,
            channels: null,
            includeVariants: true,
            entityType: ExportEntityType::Product,
            objectTypeId: null,
        );
        $session->assignTenant($tenant);

        return $session;
    }
}
