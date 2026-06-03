<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\Repository\ObjectValueRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ObjectValue>
 */
class DoctrineObjectValueRepository extends ServiceEntityRepository implements ObjectValueRepositoryInterface
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

    /**
     * @param list<Uuid> $objectIds
     *
     * @return array<string, list<ObjectValue>> keyed by object UUID (RFC 4122)
     */
    public function findByObjectIds(array $objectIds): array
    {
        if ([] === $objectIds) {
            return [];
        }

        $rfcIds = array_map(static fn (Uuid $id): string => $id->toRfc4122(), $objectIds);

        /** @var list<ObjectValue> $rows */
        $rows = $this->createQueryBuilder('v')
            ->join('v.object', 'o', Join::WITH, 'o.id IN (:ids)')
            ->setParameter('ids', $rfcIds)
            ->getQuery()
            ->getResult();

        $byObject = [];
        foreach ($rows as $row) {
            $key = $row->getObject()->getId()->toRfc4122();
            $byObject[$key][] = $row;
        }

        return $byObject;
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

    public function findById(Uuid $id): ?ObjectValue
    {
        return parent::find($id->toRfc4122());
    }

    public function save(ObjectValue $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(ObjectValue $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
