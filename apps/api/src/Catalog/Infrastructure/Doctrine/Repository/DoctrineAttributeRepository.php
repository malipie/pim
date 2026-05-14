<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attribute>
 */
class DoctrineAttributeRepository extends ServiceEntityRepository implements AttributeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attribute::class);
    }

    public function findByCode(string $code, Tenant $tenant): ?Attribute
    {
        return $this->findOneBy(['code' => $code, 'tenant' => $tenant]);
    }

    public function findById(\Symfony\Component\Uid\Uuid $id): ?Attribute
    {
        return parent::find($id->toRfc4122());
    }

    public function save(Attribute $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(Attribute $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }

    public function filterableCodes(): array
    {
        /** @var list<array{code: string}> $rows */
        $rows = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('DISTINCT a.code')
            ->from(Attribute::class, 'a')
            ->where('a.isFilterable = true')
            ->getQuery()
            ->getScalarResult();

        $codes = [];
        foreach ($rows as $row) {
            $codes[] = $row['code'];
        }

        return $codes;
    }
}
