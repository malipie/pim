<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\Doctrine\Repository;

use App\Channel\Domain\Entity\Currency;
use App\Channel\Domain\Repository\CurrencyRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Currency>
 */
class DoctrineCurrencyRepository extends ServiceEntityRepository implements CurrencyRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Currency::class);
    }

    public function findByCode(string $code): ?Currency
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findById(\Symfony\Component\Uid\Uuid $id): ?Currency
    {
        return parent::find($id->toRfc4122());
    }

    public function save(Currency $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(Currency $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
