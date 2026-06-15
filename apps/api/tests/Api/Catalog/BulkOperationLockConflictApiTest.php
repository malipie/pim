<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\Entity\BulkSession;
use App\Shared\Application\BulkOperationLock;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * IMP2-2.9 (#1485, ADR-0019 D11) — every catalog bulk-write entry point shares
 * the per-tenant {@see BulkOperationLock} with imports. When the lock is held
 * (an import or another bulk op is running), a second bulk request must fail
 * fast with HTTP 409, not queue or run concurrently. The matrix is documented
 * in `docs/architecture/concurrency-matrix.md`.
 */
final class BulkOperationLockConflictApiTest extends CatalogApiTestCase
{
    #[Test]
    public function bulkEditReturns409WhenLockHeld(): void
    {
        $client = $this->authenticatedClient();
        $lock = $this->holdBulkLock();

        try {
            $client->request('POST', '/api/products/bulk-edit', [
                'headers' => ['content-type' => 'application/json'],
                'body' => json_encode([
                    'operation' => 'toggle_enabled',
                    'product_ids' => [Uuid::v7()->toRfc4122()],
                    'payload' => ['enabled' => true],
                ], JSON_THROW_ON_ERROR),
            ]);

            self::assertResponseStatusCodeSame(409);
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function bulkActionsApplyReturns409WhenLockHeld(): void
    {
        $client = $this->authenticatedClient();
        $lock = $this->holdBulkLock();

        try {
            $client->request('POST', '/api/products/bulk-actions/set_attribute', [
                'headers' => ['content-type' => 'application/json'],
                'body' => json_encode([
                    'target_ids' => [Uuid::v7()->toRfc4122()],
                    'payload' => ['attribute_code' => 'name', 'value' => 'x'],
                ], JSON_THROW_ON_ERROR),
            ]);

            self::assertResponseStatusCodeSame(409);
        } finally {
            $lock->release();
        }
    }

    #[Test]
    public function bulkSessionRollbackReturns409WhenLockHeld(): void
    {
        $sessionId = $this->seedBulkSession();
        $client = $this->authenticatedClient();
        $lock = $this->holdBulkLock();

        try {
            $client->request('POST', \sprintf('/api/bulk-sessions/%s/rollback', $sessionId), [
                'headers' => ['content-type' => 'application/json'],
                'body' => '{}',
            ]);

            self::assertResponseStatusCodeSame(409);
        } finally {
            $lock->release();
        }
    }

    private function holdBulkLock(): LockInterface
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $lock = self::getContainer()->get(BulkOperationLock::class)->acquire($tenant);
        self::assertNotNull($lock, 'precondition: the test holds the bulk lock');

        return $lock;
    }

    private function seedBulkSession(): string
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $session = new BulkSession(
            actionType: 'set_attribute',
            targetObjectIds: [Uuid::v7()->toRfc4122()],
            actionPayload: ['attribute_code' => 'name', 'value' => 'x'],
        );
        $session->assignTenant($tenant);
        $em->persist($session);
        $em->flush();

        return $session->getId()->toRfc4122();
    }
}
