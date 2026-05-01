<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\EventListener;

use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;

/**
 * UI-08.3 (#258) — auto-attach the system audit AttributeGroup to every
 * newly-persisted ObjectType.
 *
 * The migration `Version20260501120000` seeds the audit group for every
 * tenant + back-fills `object_type_attribute_groups` for ObjectTypes that
 * already exist. This listener handles the *runtime* path: any ObjectType
 * created later (a future custom kind in Faza 2/3, or a fixture seeder run
 * after the migration) gets the audit group wired in automatically.
 *
 * Tenant scope: the lookup uses `findByCode('audit', $tenant)` after
 * `postPersist` has stamped the `ObjectType.tenant`, so a brand-new tenant
 * that has not been seeded yet is a no-op (the missing audit group is the
 * caller's responsibility to seed first — see {@see \App\Catalog\Application\BuiltInSystemAttributesSeeder}).
 *
 * The listener uses a separate flush from the original ObjectType persist
 * because the junction row references the just-persisted ObjectType id;
 * Doctrine's UnitOfWork guarantees `postPersist` fires after the row is in
 * the database. We persist the junction inside the same EntityManager and
 * call `flush()` in a guarded block to avoid recursion.
 */
#[AsDoctrineListener(event: Events::postPersist)]
final class AutoAttachAuditGroupListener
{
    private bool $attaching = false;

    public function __construct(
        private readonly AttributeGroupRepositoryInterface $attributeGroupRepository,
    ) {
    }

    public function postPersist(PostPersistEventArgs $event): void
    {
        $entity = $event->getObject();

        if (!$entity instanceof ObjectType) {
            return;
        }

        if ($this->attaching) {
            return;
        }

        $tenant = $entity->getTenant();
        if (null === $tenant) {
            return;
        }

        $auditGroup = $this->attributeGroupRepository->findByCode('audit', $tenant);
        if (!$auditGroup instanceof AttributeGroup || !$auditGroup->isSystemGroup()) {
            return;
        }

        $em = $event->getObjectManager();
        $junction = new ObjectTypeAttributeGroup($entity, $auditGroup, position: 999);

        $this->attaching = true;
        try {
            $em->persist($junction);
            $em->flush();
        } finally {
            $this->attaching = false;
        }
    }
}
