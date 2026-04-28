<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ObjectValue>
 */
class ObjectValueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ObjectValue::class);
    }

    /**
     * @return list<ObjectValue>
     */
    public function findByObject(CatalogObject $object): array
    {
        /** @var list<ObjectValue> $rows */
        $rows = $this->findBy(['object' => $object]);

        return $rows;
    }

    public function findOneByScope(
        CatalogObject $object,
        Attribute $attribute,
        ?Uuid $channelId = null,
        ?string $locale = null,
    ): ?ObjectValue {
        return $this->findOneBy([
            'object' => $object,
            'attribute' => $attribute,
            'channelId' => $channelId,
            'locale' => $locale,
        ]);
    }
}
