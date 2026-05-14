<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Entity\UserFilterFavorite;
use App\Identity\Domain\Repository\UserFilterFavoriteRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<UserFilterFavorite>
 */
final class DoctrineUserFilterFavoriteRepository extends ServiceEntityRepository implements UserFilterFavoriteRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserFilterFavorite::class);
    }

    public function findByUser(User $user): array
    {
        /** @var list<UserFilterFavorite> $rows */
        $rows = $this->createQueryBuilder('f')
            ->where('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function replaceForUser(User $user, array $entries): void
    {
        $em = $this->getEntityManager();
        $em->wrapInTransaction(static function () use ($em, $user, $entries): void {
            $em->createQueryBuilder()
                ->delete(UserFilterFavorite::class, 'f')
                ->where('f.user = :user')
                ->setParameter('user', $user)
                ->getQuery()
                ->execute();

            foreach ($entries as $entry) {
                $attribute = $em->getReference(Attribute::class, Uuid::fromString($entry['attribute_id']));
                $favorite = new UserFilterFavorite($user, $attribute, $entry['sort_order']);
                $em->persist($favorite);
            }
            $em->flush();
        });
    }

    public function save(UserFilterFavorite $favorite): void
    {
        $em = $this->getEntityManager();
        $em->persist($favorite);
        $em->flush();
    }
}
