<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\Doctrine\Repository;

use App\Channel\Domain\Entity\Locale;
use App\Channel\Domain\Repository\LocaleRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Locale>
 */
class DoctrineLocaleRepository extends ServiceEntityRepository implements LocaleRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Locale::class);
    }

    public function findByCode(string $code): ?Locale
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function findById(\Symfony\Component\Uid\Uuid $id): ?Locale
    {
        return parent::find($id->toRfc4122());
    }

    public function save(Locale $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(Locale $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
