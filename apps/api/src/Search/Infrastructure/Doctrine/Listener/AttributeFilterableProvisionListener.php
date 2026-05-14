<?php

declare(strict_types=1);

namespace App\Search\Infrastructure\Doctrine\Listener;

use App\Catalog\Domain\Entity\Attribute;
use App\Search\Application\MeilisearchIndexProvisioner;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * VIEW-38 (#579) — reprovisions Meilisearch index settings whenever an
 * Attribute's `is_filterable` flag is added (postPersist with true),
 * toggled (preUpdate sees the field in the change set), or removed
 * (preRemove on a still-filterable row). Without this hook the new
 * flag would only take effect on the next stack restart.
 *
 * Flow:
 *  1. Doctrine fires one of the lifecycle events above for an Attribute
 *     mutation that actually changes the filterable set.
 *  2. The hook sets `pending=true`; the real Meili `updateSettings`
 *     round-trip is deferred to `postFlush` so a batch of changes
 *     (importer, migration) issues a single network call.
 *  3. `postFlush` calls `MeilisearchIndexProvisioner::provision()`,
 *     which re-applies the settings template (idempotent on Meili side).
 *
 * Failures are logged and swallowed — Meili being unreachable must
 * not block the Postgres write. Settings catch up on the next provision
 * call or stack boot.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::preUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
final class AttributeFilterableProvisionListener
{
    private bool $pending = false;
    private LoggerInterface $logger;

    public function __construct(
        private readonly MeilisearchIndexProvisioner $provisioner,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Attribute && $entity->isFilterable()) {
            $this->pending = true;
        }
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof Attribute) {
            return;
        }
        // Only react when the filterable flag itself is part of the
        // changeset. Label / position / validation-rule edits run
        // hundreds of times during a typical session and must not
        // trigger a Meili settings refresh.
        if (\array_key_exists('isFilterable', $args->getEntityChangeSet())) {
            $this->pending = true;
        }
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($entity instanceof Attribute && $entity->isFilterable()) {
            $this->pending = true;
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (!$this->pending) {
            return;
        }
        $this->pending = false;
        try {
            $this->provisioner->provision();
        } catch (Throwable $e) {
            $this->logger->warning('Meili reprovision after Attribute change failed: {message}', [
                'message' => $e->getMessage(),
            ]);
        }
    }
}
