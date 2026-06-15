<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Application\Handler\RebuildAttributesIndexedHandler;
use App\Catalog\Application\Message\ObjectValuesChangedMessage;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Uid\Uuid;

/**
 * IMP2-2.9 (#1485, ADR-0019 D11) — the async rebuild flushes PER ID, so a
 * version conflict on one object (a concurrent UI edit bumped `objects.version`
 * between read and flush) is retried with a fresh read instead of dead-lettering
 * the whole batch.
 *
 * Deterministic conflict: the three objects stay in the identity map at
 * `version = 1`; a raw `UPDATE` bumps the DB to `version = 2` behind Doctrine's
 * back. The handler's first `find()` returns the stale managed instance → its
 * flush hits the version guard and raises `OptimisticLockException`; the handler
 * clears the unit of work, re-reads the fresh row, and succeeds on retry. After
 * the first id's clear the remaining ids read fresh and flush cleanly — so every
 * object ends at `version = 3` (1 seeded → 2 raw bump → 3 rebuild flush) and the
 * handler never propagates the exception.
 */
final class RebuildAttributesIndexedOptimisticRetryTest extends CatalogApiTestCase
{
    #[Test]
    public function versionConflictOnOneObjectIsRetriedNotDeadLettered(): void
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $productOt = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert($productOt instanceof ObjectType);

        $ids = [];
        foreach (['RETRY-A', 'RETRY-B', 'RETRY-C'] as $code) {
            $object = new CatalogObject($productOt, $code);
            $em->persist($object);
            $ids[] = $object->getId();
        }
        $em->flush();
        // Keep the three instances managed at version=1 (NO clear): the handler's
        // find() must return these stale instances, not a fresh DB read.

        // Bump the DB version behind Doctrine's back — emulates a concurrent edit
        // committed between the handler's read and its flush.
        $conn = $em->getConnection();
        foreach ($ids as $id) {
            $conn->executeStatement(
                'UPDATE objects SET version = version + 1 WHERE id = :id',
                ['id' => $id->toRfc4122()],
            );
        }

        $handler = self::getContainer()->get(RebuildAttributesIndexedHandler::class);
        \assert($handler instanceof RebuildAttributesIndexedHandler);

        // Must NOT throw — the per-id retry absorbs the conflict.
        $handler(new ObjectValuesChangedMessage(array_map(
            static fn (Uuid $id): string => $id->toRfc4122(),
            $ids,
        )));

        // Every object rebuilt + flushed exactly once after the raw bump: 1
        // (seed) → 2 (raw) → 3 (rebuild). version=3 on the FIRST id proves the
        // conflict was retried to success, not skipped or propagated.
        $em->clear();
        foreach ($ids as $id) {
            $version = $conn->fetchOne(
                'SELECT version FROM objects WHERE id = :id',
                ['id' => $id->toRfc4122()],
            );
            self::assertSame(3, (int) (\is_scalar($version) ? $version : 0), 'each object rebuilt once after the conflict');
        }
    }
}
