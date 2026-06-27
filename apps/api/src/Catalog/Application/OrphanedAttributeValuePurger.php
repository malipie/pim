<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Application\Reindex\BulkReindexQueueInterface;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Deletes an Attribute together with its ORPHANED `object_values` and then
 * the attribute row itself, keeping the denormalised cache and the search
 * index consistent.
 *
 * "Orphaned" means the attribute is attached to no ObjectType, no
 * AttributeGroup and no Category overlay — the caller
 * ({@see Command\DeleteAttribute\DeleteAttributeHandler})
 * guarantees that precondition. Such values are unreachable from any form:
 * once an attribute is detached it disappears from the editor, so its values
 * can never be cleared through the UI and would otherwise block deletion
 * forever (the `object_values.attribute_id` FK is ON DELETE RESTRICT).
 *
 * Mirrors the proven {@see \App\Import\Application\Service\ImportRollbackService}
 * shape: set-based DELETE inside a transaction, per-object cache rebuild in
 * memory-bounded chunks (FrankenPHP worker mode), Meilisearch refresh queued
 * after the transaction commits.
 */
final readonly class OrphanedAttributeValuePurger
{
    private const int CHUNK = 200;

    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection,
        private AttributesIndexedRebuilder $rebuilder,
        private BulkReindexQueueInterface $reindexQueue,
    ) {
    }

    /**
     * Removes the attribute's orphaned values, rebuilds the affected objects'
     * `attributes_indexed`, and deletes the attribute — all atomically.
     */
    public function purgeAndDelete(Attribute $attribute): void
    {
        $attributeId = $attribute->getId()->toRfc4122();

        // tenant-safe: explicit tenant_id filter — the attribute was loaded
        // through the TenantFilter-aware repository, so its id is already
        // tenant-scoped; object_values carries tenant_id and we constrain on it.
        $affectedIds = array_values(array_filter(
            array_map(
                static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '',
                $this->connection->fetchFirstColumn(
                    'SELECT DISTINCT object_id FROM object_values WHERE attribute_id = ? AND tenant_id = ?',
                    [$attributeId, $attribute->getTenant()?->getId()->toRfc4122()],
                ),
            ),
            static fn (string $id): bool => '' !== $id,
        ));

        $this->em->wrapInTransaction(function () use ($attributeId, $attribute, $affectedIds): void {
            // tenant-safe: explicit tenant_id filter; set-based purge of values
            // that are unreachable from any form (attribute detached everywhere).
            $this->connection->executeStatement(
                'DELETE FROM object_values WHERE attribute_id = ? AND tenant_id = ?',
                [$attributeId, $attribute->getTenant()?->getId()->toRfc4122()],
            );

            foreach (array_chunk($affectedIds, self::CHUNK) as $chunk) {
                foreach ($chunk as $idRfc) {
                    $object = $this->em->find(CatalogObject::class, Uuid::fromString($idRfc));
                    if ($object instanceof CatalogObject) {
                        // Drops the now-deleted attribute's key from the JSONB
                        // cache and recomputes completeness from the survivors.
                        $this->rebuilder->rebuild($object);
                    }
                }
                $this->em->flush();
                $this->em->clear();
            }

            // Re-find: the chunk `clear()` above detached the original entity.
            $managed = $this->em->find(Attribute::class, Uuid::fromString($attributeId));
            if ($managed instanceof Attribute) {
                $this->em->remove($managed);
                $this->em->flush();
            }
        });

        // Outside the transaction: refresh the surviving objects' Meili docs so
        // the dropped attribute field disappears from the index.
        $this->reindexQueue->queueAll($affectedIds);
    }
}
