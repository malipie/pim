<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelObjectTypeMapping;
use App\Channel\Domain\Repository\ChannelObjectTypeMappingRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChannelObjectTypeMapping>
 */
class DoctrineChannelObjectTypeMappingRepository extends ServiceEntityRepository implements ChannelObjectTypeMappingRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChannelObjectTypeMapping::class);
    }

    /**
     * @return list<ChannelObjectTypeMapping>
     */
    public function findByChannelAndObjectType(Channel $channel, ObjectType $objectType): array
    {
        /** @var list<ChannelObjectTypeMapping> $rows */
        $rows = $this->findBy([
            'channel' => $channel,
            'objectType' => $objectType,
        ]);

        return $rows;
    }

    public function findOne(Channel $channel, ObjectType $objectType, Attribute $attribute): ?ChannelObjectTypeMapping
    {
        return $this->findOneBy([
            'channel' => $channel,
            'objectType' => $objectType,
            'attribute' => $attribute,
        ]);
    }

    public function findById(\Symfony\Component\Uid\Uuid $id): ?ChannelObjectTypeMapping
    {
        return parent::find($id->toRfc4122());
    }

    public function save(ChannelObjectTypeMapping $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(ChannelObjectTypeMapping $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
