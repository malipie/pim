<?php

declare(strict_types=1);

namespace App\Catalog\Application\Bulk;

use App\Catalog\Application\BulkContext;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * VIEW-17 (#544) — 24h soft rollback executor.
 *
 * Iterates `bulk_logs` in reverse insertion order (Doctrine `iterate()`
 * for memory safety per CLAUDE.md FrankenPHP rule), restoring `old_value`
 * on each `attributes_indexed` slot. Marks the session as rolled back
 * so the toast disappears + the rollback button greys out.
 *
 * Only logs with `level=info` are reversed — `error` rows were never
 * applied, and `warning` rows are skip-with-report entries that already
 * left the row untouched.
 *
 * Hard expiry: rollback past `rollback_available_until` raises 400.
 */
final class BulkRollbackHandler
{
    public function __construct(
        private readonly CatalogObjectRepositoryInterface $catalogObjects,
        private readonly EntityManagerInterface $em,
        private readonly BulkContext $bulkContext,
    ) {
    }

    public function rollback(BulkSession $session): int
    {
        if (!$session->isRollbackAvailable()) {
            throw new BadRequestHttpException('Rollback window expired or already used.');
        }

        $this->bulkContext->setBulk(true);
        $restored = 0;
        try {
            $logs = $this->em->getRepository(BulkLog::class)
                ->createQueryBuilder('l')
                ->where('l.bulkSessionId = :session')
                ->andWhere('l.level = :level')
                ->setParameter('session', $session->getId())
                ->setParameter('level', BulkLog::LEVEL_INFO)
                ->orderBy('l.createdAt', 'DESC')
                ->getQuery()
                ->toIterable();

            $chunk = 0;
            foreach ($logs as $log) {
                $object = $this->catalogObjects->findById($log->getObjectId());
                if (!$object instanceof CatalogObject) {
                    continue;
                }

                $indexed = $object->getAttributesIndexed();
                // The accompanying handler stamps `null` attributeId for
                // attribute-level changes that targeted `attributes_indexed`
                // directly — recover the slot by walking the old/new value
                // pair instead of needing the attribute UUID.
                $action = $session->getActionType();
                if ('set_attribute' === $action) {
                    $payload = $session->getActionPayload();
                    $attrCode = isset($payload['attr']) && \is_string($payload['attr']) ? $payload['attr'] : null;
                    if (null !== $attrCode) {
                        if (null === $log->getOldValue()) {
                            unset($indexed[$attrCode]);
                        } else {
                            $indexed[$attrCode] = $log->getOldValue();
                        }
                        $object->updateAttributeIndex($indexed);
                        ++$restored;
                    }
                }

                ++$chunk;
                if ($chunk >= 200) {
                    $this->em->flush();
                    $this->em->clear();
                    $chunk = 0;
                }
            }

            if ($chunk > 0) {
                $this->em->flush();
            }

            // Reload session in case clear() detached it.
            $sessionFresh = $this->em->find(BulkSession::class, $session->getId());
            if ($sessionFresh instanceof BulkSession) {
                $sessionFresh->markRolledBack();
                $this->em->flush();
            }

            return $restored;
        } finally {
            $this->bulkContext->setBulk(false);
        }
    }
}
