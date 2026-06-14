<?php

declare(strict_types=1);

namespace App\Asset\Infrastructure\Doctrine\Repository;

use App\Asset\Domain\Entity\Asset;
use App\Asset\Domain\Repository\AssetRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Stringable;

/**
 * @extends ServiceEntityRepository<Asset>
 */
class DoctrineAssetRepository extends ServiceEntityRepository implements AssetRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Asset::class);
    }

    public function findByCode(string $code, Tenant $tenant): ?Asset
    {
        return $this->findOneBy(['code' => $code, 'tenant' => $tenant]);
    }

    public function findByContentHash(string $contentHash, Tenant $tenant): ?Asset
    {
        return $this->findOneBy(['contentHash' => $contentHash, 'tenant' => $tenant]);
    }

    public function findByObjectId(\Symfony\Component\Uid\Uuid $objectId): ?Asset
    {
        return $this->findOneBy(['objectId' => $objectId->toRfc4122()]);
    }

    public function findById(\Symfony\Component\Uid\Uuid $id): ?Asset
    {
        return parent::find($id->toRfc4122());
    }

    public function existingIds(array $rfc4122Ids, Tenant $tenant): array
    {
        if ([] === $rfc4122Ids) {
            return [];
        }

        /** @var list<mixed> $rows */
        $rows = $this->createQueryBuilder('a')
            ->select('a.id')
            ->where('a.id IN (:ids)')
            ->andWhere('a.tenant = :tenant')
            ->setParameter('ids', array_values(array_unique($rfc4122Ids)))
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->getSingleColumnResult();

        return array_map(
            static fn (mixed $id): string => $id instanceof Stringable || \is_scalar($id) ? (string) $id : '',
            $rows,
        );
    }

    public function save(Asset $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(Asset $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
