<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\Doctrine\Repository;

use App\Channel\Domain\Entity\ChannelPublicationProfile;
use App\Channel\Domain\Repository\ChannelPublicationProfileRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ChannelPublicationProfile>
 */
class DoctrineChannelPublicationProfileRepository extends ServiceEntityRepository implements ChannelPublicationProfileRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChannelPublicationProfile::class);
    }

    public function findById(Uuid $id): ?ChannelPublicationProfile
    {
        return parent::find($id->toRfc4122());
    }

    public function findByChannelAndObjectType(
        Uuid $channelId,
        Uuid $objectTypeId,
        Tenant $tenant,
    ): ?ChannelPublicationProfile {
        return $this->findOneBy([
            'channelId' => $channelId,
            'objectTypeId' => $objectTypeId,
            'tenant' => $tenant,
        ]);
    }

    /**
     * @return list<ChannelPublicationProfile>
     */
    public function findForChannel(Uuid $channelId, Tenant $tenant): array
    {
        /* @var list<ChannelPublicationProfile> */
        return $this->findBy(['channelId' => $channelId, 'tenant' => $tenant]);
    }

    /**
     * @return list<ChannelPublicationProfile>
     */
    public function findForTenant(Tenant $tenant): array
    {
        /* @var list<ChannelPublicationProfile> */
        return $this->findBy(['tenant' => $tenant]);
    }

    public function save(ChannelPublicationProfile $profile): void
    {
        $em = $this->getEntityManager();
        $em->persist($profile);
        $em->flush();
    }

    public function remove(ChannelPublicationProfile $profile): void
    {
        $em = $this->getEntityManager();
        $em->remove($profile);
        $em->flush();
    }
}
