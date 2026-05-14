<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Doctrine\Repository;

use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\UserFilterFavorite;
use App\Catalog\Domain\Repository\UserFilterFavoriteRepositoryInterface;
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

    public function findByUser(Uuid $userId): array
    {
        /** @var list<UserFilterFavorite> $rows */
        $rows = $this->createQueryBuilder('f')
            ->where('f.userId = :user')
            ->setParameter('user', $userId, 'uuid')
            ->orderBy('f.sortOrder', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function replaceForUser(Uuid $userId, array $entries): void
    {
        $em = $this->getEntityManager();
        $em->wrapInTransaction(static function () use ($em, $userId, $entries): void {
            $em->createQueryBuilder()
                ->delete(UserFilterFavorite::class, 'f')
                ->where('f.userId = :user')
                ->setParameter('user', $userId, 'uuid')
                ->getQuery()
                ->execute();

            foreach ($entries as $entry) {
                $attribute = $em->getReference(Attribute::class, Uuid::fromString($entry['attribute_id']));
                if (!$attribute instanceof Attribute) {
                    continue;
                }
                $favorite = new UserFilterFavorite($userId, $attribute, $entry['sort_order']);
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
