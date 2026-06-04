<?php

declare(strict_types=1);

namespace App\Channel\Domain\Repository;

use App\Channel\Domain\Entity\ChannelPublicationProfile;
use App\Shared\Domain\Tenant;
use Symfony\Component\Uid\Uuid;

interface ChannelPublicationProfileRepositoryInterface
{
    public function findById(Uuid $id): ?ChannelPublicationProfile;

    public function findByChannelAndObjectType(
        Uuid $channelId,
        Uuid $objectTypeId,
        Tenant $tenant,
    ): ?ChannelPublicationProfile;

    /**
     * @return list<ChannelPublicationProfile>
     */
    public function findForChannel(Uuid $channelId, Tenant $tenant): array;

    /**
     * @return list<ChannelPublicationProfile>
     */
    public function findForTenant(Tenant $tenant): array;

    public function save(ChannelPublicationProfile $profile): void;

    public function remove(ChannelPublicationProfile $profile): void;
}
