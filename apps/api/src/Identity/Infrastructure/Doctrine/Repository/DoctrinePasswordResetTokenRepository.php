<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\PasswordResetToken;
use App\Identity\Domain\Repository\PasswordResetTokenRepositoryInterface;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<PasswordResetToken>
 */
class DoctrinePasswordResetTokenRepository extends ServiceEntityRepository implements PasswordResetTokenRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PasswordResetToken::class);
    }

    public function findById(Uuid $id): ?PasswordResetToken
    {
        return parent::find($id->toRfc4122());
    }

    public function findByHash(string $tokenHash): ?PasswordResetToken
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function save(PasswordResetToken $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(PasswordResetToken $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }

    public function purgeStale(DateTimeImmutable $cutoff): int
    {
        $qb = $this->createQueryBuilder('t');
        $qb->delete()
            ->where($qb->expr()->orX(
                $qb->expr()->lte('t.expiresAt', ':cutoff'),
                $qb->expr()->lte('t.usedAt', ':cutoff'),
            ))
            ->setParameter('cutoff', $cutoff);

        return $qb->getQuery()->execute();
    }
}
