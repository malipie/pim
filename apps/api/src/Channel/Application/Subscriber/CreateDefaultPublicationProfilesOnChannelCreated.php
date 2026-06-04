<?php

declare(strict_types=1);

namespace App\Channel\Application\Subscriber;

use App\Channel\Contracts\Event\ChannelCreated;
use App\Channel\Domain\Entity\ChannelPublicationProfile;
use App\Channel\Domain\Repository\ChannelPublicationProfileRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * On {@see ChannelCreated}: provisions one publish-all default profile per
 * ObjectType that belongs to the same tenant. ADR-0018.
 *
 * Uses raw DBAL to list ObjectType IDs — no Doctrine cross-BC entity
 * reference (Deptrac-safe; Channel → Catalog_Contracts only).
 */
#[AsMessageHandler]
final readonly class CreateDefaultPublicationProfilesOnChannelCreated
{
    public function __construct(
        private ChannelPublicationProfileRepositoryInterface $profiles,
        private EntityManagerInterface $entityManager,
        private Connection $connection,
    ) {
    }

    public function __invoke(ChannelCreated $event): void
    {
        $channelId = $event->channelId;
        $tenantId = $event->tenantId->toRfc4122();

        $tenant = $this->entityManager->find(Tenant::class, $event->tenantId->toRfc4122());
        if (null === $tenant) {
            return;
        }

        $objectTypeIds = $this->connection->fetchFirstColumn(
            'SELECT id FROM object_types WHERE tenant_id = ?',
            [$tenantId],
        );

        foreach ($objectTypeIds as $rawId) {
            \assert(\is_string($rawId) && '' !== $rawId);
            $objectTypeId = Uuid::fromString($rawId);
            $existing = $this->profiles->findByChannelAndObjectType($channelId, $objectTypeId, $tenant);
            if (null !== $existing) {
                continue;
            }
            $this->profiles->save(new ChannelPublicationProfile(
                channelId: $channelId,
                objectTypeId: $objectTypeId,
                publishedAttributeCodes: null,
                publishedLocales: [],
                columnAliases: [],
                isDefault: true,
                tenant: $tenant,
            ));
        }
    }
}
