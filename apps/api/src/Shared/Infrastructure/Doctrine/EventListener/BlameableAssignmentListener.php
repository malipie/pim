<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Doctrine\EventListener;

use App\Shared\Application\Blameable;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Stamps every {@see Blameable} entity with the current actor's identifier
 * (e-mail) on write: both `createdBy` and `updatedBy` on insert, only
 * `updatedBy` on update.
 *
 * Dispatches by interface like {@see TenantAssignmentListener} — entities opt
 * in by implementing Blameable. Runs on `onFlush` (not pre-persist/pre-update)
 * so a single hook covers inserts and updates and the changeset can be
 * recomputed cleanly after mutating the fields.
 *
 * The actor comes from Symfony Security (a framework concern, available in
 * every context), NOT from the Identity domain — so the owning context keeps
 * no cross-context dependency. Writes with no security context (CLI seeders,
 * async import workers) leave the fields untouched (null), which the read
 * layer renders as "—".
 */
#[AsDoctrineListener(event: Events::onFlush)]
final readonly class BlameableAssignmentListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function onFlush(OnFlushEventArgs $event): void
    {
        $actor = $this->currentActor();
        if (null === $actor) {
            return;
        }

        $em = $event->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if (!$entity instanceof Blameable) {
                continue;
            }
            if (null === $entity->getCreatedBy()) {
                $entity->stampCreatedBy($actor);
            }
            $entity->stampUpdatedBy($actor);
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Blameable) {
                continue;
            }
            $entity->stampUpdatedBy($actor);
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata($entity::class), $entity);
        }
    }

    private function currentActor(): ?string
    {
        // UserInterface::getUserIdentifier() is the e-mail for this app's User.
        // Null token / no user (CLI seeders, async import) → no stamp.
        return $this->tokenStorage->getToken()?->getUser()?->getUserIdentifier();
    }
}
