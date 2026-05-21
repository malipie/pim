<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\Doctrine\Repository;

use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Entity\TenantLocale;
use App\Channel\Domain\Repository\TenantLocaleRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<TenantLocale>
 */
class DoctrineTenantLocaleRepository extends ServiceEntityRepository implements TenantLocaleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TenantLocale::class);
    }

    public function findById(Uuid $id): ?TenantLocale
    {
        return parent::find($id->toRfc4122());
    }

    public function findByTenantAndLocale(Tenant $tenant, Locale $locale): ?TenantLocale
    {
        return $this->findOneBy([
            'tenant' => $tenant,
            'locale' => $locale,
        ]);
    }

    public function findByTenantAndCode(Tenant $tenant, string $code): ?TenantLocale
    {
        /** @var TenantLocale|null $row */
        $row = $this->createQueryBuilder('tl')
            ->innerJoin('tl.locale', 'l')
            ->where('tl.tenant = :tenant')
            ->andWhere('l.code = :code')
            ->setParameter('tenant', $tenant)
            ->setParameter('code', $code)
            ->getQuery()
            ->getOneOrNullResult();

        return $row;
    }

    public function findActiveForTenant(Tenant $tenant): array
    {
        /** @var list<TenantLocale> $rows */
        $rows = $this->createQueryBuilder('tl')
            ->where('tl.tenant = :tenant')
            ->andWhere('tl.isActive = true')
            ->orderBy('tl.sortOrder', 'ASC')
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findAllForTenant(Tenant $tenant): array
    {
        /** @var list<TenantLocale> $rows */
        $rows = $this->createQueryBuilder('tl')
            ->where('tl.tenant = :tenant')
            ->orderBy('tl.sortOrder', 'ASC')
            ->setParameter('tenant', $tenant)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function findDefaultForTenant(Tenant $tenant): ?TenantLocale
    {
        return $this->findOneBy([
            'tenant' => $tenant,
            'isDefault' => true,
        ]);
    }

    public function save(TenantLocale $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(TenantLocale $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
