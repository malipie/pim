<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Reverses an import within the 24h rollback window — deletes every
 * CatalogObject + ObjectValue stamped with the session id and flips
 * the session status to `rolled_back`.
 *
 * The delete is hard, not soft, because the catalog has no soft-delete
 * column today and re-importing the same SKU after rollback must
 * succeed (`DuplicateSkuInDb` would otherwise fire). Spec §7.7
 * describes a "soft rollback" pattern with a 7-day restore window —
 * lining that up needs `deleted_at` everywhere; deferred to Phase 2
 * once `pending_changes` is wired.
 *
 * Linked Asset rows stay untouched on purpose (spec §7.7: "klient
 * ręcznie usuwa w DAM"). The 24h window guard lives on the entity;
 * this service just orchestrates the DBAL DELETE.
 */
final readonly class ImportRollbackService
{
    public function __construct(
        private EntityManagerInterface $em,
        private Connection $connection,
        private ImportSessionRepositoryInterface $sessions,
    ) {
    }

    /**
     * @return array{deletedObjects: int, deletedValues: int}
     */
    public function rollback(ImportSession $session): array
    {
        $session->markRolledBack();

        try {
            $this->connection->beginTransaction();
            $sessionId = $session->getId()->toRfc4122();

            $deletedValues = (int) $this->connection->executeStatement(
                <<<'SQL'
                        DELETE FROM object_values
                        WHERE object_id IN (
                            SELECT id FROM objects WHERE import_session_id = :session_id
                        )
                    SQL,
                ['session_id' => $sessionId],
            );

            $deletedObjects = (int) $this->connection->executeStatement(
                'DELETE FROM objects WHERE import_session_id = :session_id',
                ['session_id' => $sessionId],
            );

            $this->connection->commit();
        } catch (DBALException $exception) {
            if ($this->connection->isTransactionActive()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }

        // The session row was mutated through the EM (markRolledBack), but
        // the bulk DELETEs ran via raw DBAL — re-merge so flush stamps
        // `rolled_back_at` + `status='rolled_back'` cleanly.
        $this->em->clear();
        $reload = $this->sessions->findById($session->getId());
        if (null !== $reload) {
            $reload->markRolledBack();
            $this->sessions->save($reload);
        }

        return [
            'deletedObjects' => $deletedObjects,
            'deletedValues' => $deletedValues,
        ];
    }
}
