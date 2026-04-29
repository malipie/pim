<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\ObjectType;
use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelObjectTypeMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChannelObjectTypeMapping>
 */
class ChannelObjectTypeMappingRepository extends ServiceEntityRepository
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
}
