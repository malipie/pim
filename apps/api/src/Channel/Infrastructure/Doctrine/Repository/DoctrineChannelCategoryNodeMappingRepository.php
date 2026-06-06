<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\Doctrine\Repository;

use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelCategoryNodeMapping;
use App\Channel\Domain\Repository\ChannelCategoryNodeMappingRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ChannelCategoryNodeMapping>
 */
class DoctrineChannelCategoryNodeMappingRepository extends ServiceEntityRepository implements ChannelCategoryNodeMappingRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChannelCategoryNodeMapping::class);
    }

    /**
     * @return list<ChannelCategoryNodeMapping>
     */
    public function findByChannel(Channel $channel): array
    {
        return $this->findBy(['channel' => $channel], ['masterCategoryId' => 'ASC']);
    }

    public function findByChannelAndMaster(Channel $channel, Uuid $masterCategoryId): ?ChannelCategoryNodeMapping
    {
        $result = $this->createQueryBuilder('m')
            ->andWhere('IDENTITY(m.channel) = :channelId')
            ->andWhere('m.masterCategoryId = :master')
            ->setParameter('channelId', $channel->getId(), 'uuid')
            ->setParameter('master', $masterCategoryId, 'uuid')
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof ChannelCategoryNodeMapping ? $result : null;
    }

    public function upsert(Channel $channel, Uuid $masterCategoryId, array $channelNodeIds): ChannelCategoryNodeMapping
    {
        $existing = $this->findByChannelAndMaster($channel, $masterCategoryId);
        if (null !== $existing) {
            $existing->replaceNodes($channelNodeIds);
            $this->save($existing);

            return $existing;
        }

        $mapping = new ChannelCategoryNodeMapping($channel, $masterCategoryId, $channelNodeIds);
        $this->save($mapping);

        return $mapping;
    }

    public function save(ChannelCategoryNodeMapping $mapping): void
    {
        $em = $this->getEntityManager();
        $em->persist($mapping);
        $em->flush();
    }

    public function remove(ChannelCategoryNodeMapping $mapping): void
    {
        $em = $this->getEntityManager();
        $em->remove($mapping);
        $em->flush();
    }
}
