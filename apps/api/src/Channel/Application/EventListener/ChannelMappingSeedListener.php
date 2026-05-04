<?php

declare(strict_types=1);

namespace App\Channel\Application\EventListener;

use App\Catalog\Domain\Repository\ObjectTypeAttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Channel\Contracts\Event\ChannelCreated;
use App\Channel\Domain\Entity\ChannelObjectTypeMapping;
use App\Channel\Domain\Repository\ChannelObjectTypeMappingRepositoryInterface;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * VIEW-06 (#418) — when a Channel is created, seed
 * `channel_object_type_mappings` rows for every (ObjectType × Attribute)
 * pair already attached to ObjectTypes in the tenant. `targetField`
 * starts empty so the operator can fill them via the mapping editor.
 *
 * Memory safety: walks ObjectTypes one at a time and clears the
 * EntityManager between batches to keep the FrankenPHP worker under
 * the 256 MB alert (per CLAUDE.md "Memory management"). For typical
 * seeds (~150 rows) this is a no-op; for tenant with 1000+ atrybutów
 * the periodic clear caps memory growth.
 */
#[AsMessageHandler]
final readonly class ChannelMappingSeedListener
{
    private const int FLUSH_BATCH_SIZE = 200;

    public function __construct(
        private ChannelRepositoryInterface $channels,
        private ObjectTypeRepositoryInterface $objectTypes,
        private ObjectTypeAttributeRepositoryInterface $junctions,
        private ChannelObjectTypeMappingRepositoryInterface $mappings,
        private TenantRepositoryInterface $tenants,
        private EntityManagerInterface $em,
    ) {
    }

    public function __invoke(ChannelCreated $event): void
    {
        $channel = $this->channels->findById($event->channelId);
        if (null === $channel) {
            return;
        }

        $tenant = $this->tenants->findById($event->tenantId);
        if (null === $tenant) {
            return;
        }

        $objectTypes = $this->objectTypes->findAllByTenant($tenant);
        $rowsThisBatch = 0;

        foreach ($objectTypes as $objectType) {
            $junctions = $this->junctions->findByObjectType($objectType);

            foreach ($junctions as $junction) {
                $attribute = $junction->getAttribute();

                // Idempotent: skip if a mapping already exists (race-safe
                // against future ObjectTypeAttributeAttached listener).
                $existing = $this->mappings->findOne($channel, $objectType, $attribute);
                if (null !== $existing) {
                    continue;
                }

                $mapping = new ChannelObjectTypeMapping(
                    channel: $channel,
                    objectType: $objectType,
                    attribute: $attribute,
                    targetField: '',
                    isPublished: true,
                );
                $this->em->persist($mapping);
                ++$rowsThisBatch;

                if (self::FLUSH_BATCH_SIZE === $rowsThisBatch) {
                    $this->em->flush();
                    $this->em->clear();
                    $rowsThisBatch = 0;
                }
            }
        }

        if ($rowsThisBatch > 0) {
            $this->em->flush();
        }
    }
}
