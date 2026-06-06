<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\Doctrine\Repository;

use App\Channel\Domain\ChannelPlacementSource;
use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelCategoryNode;
use App\Channel\Domain\Entity\ObjectChannelPlacement;
use App\Channel\Domain\Repository\ObjectChannelPlacementRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ObjectChannelPlacement>
 */
class DoctrineObjectChannelPlacementRepository extends ServiceEntityRepository implements ObjectChannelPlacementRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ObjectChannelPlacement::class);
    }

    /**
     * @return list<ObjectChannelPlacement>
     */
    public function findByObject(Uuid $objectId): array
    {
        return $this->findBy(['objectId' => $objectId], ['channel' => 'ASC']);
    }

    public function findByObjectAndChannel(Uuid $objectId, Uuid $channelId): ?ObjectChannelPlacement
    {
        $result = $this->createQueryBuilder('p')
            ->andWhere('p.objectId = :objectId')
            ->andWhere('IDENTITY(p.channel) = :channelId')
            ->setParameter('objectId', $objectId, 'uuid')
            ->setParameter('channelId', $channelId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof ObjectChannelPlacement ? $result : null;
    }

    public function upsert(
        Uuid $objectId,
        Channel $channel,
        ChannelCategoryNode $node,
        ChannelPlacementSource $source,
    ): ObjectChannelPlacement {
        $existing = $this->findByObjectAndChannel($objectId, $channel->getId());
        if (null !== $existing) {
            $existing->reassign($node, $source);
            $this->save($existing);

            return $existing;
        }

        $placement = new ObjectChannelPlacement($objectId, $channel, $node, $source);
        $this->save($placement);

        return $placement;
    }

    public function save(ObjectChannelPlacement $placement): void
    {
        $em = $this->getEntityManager();
        $em->persist($placement);
        $em->flush();
    }

    public function remove(ObjectChannelPlacement $placement): void
    {
        $em = $this->getEntityManager();
        $em->remove($placement);
        $em->flush();
    }
}
