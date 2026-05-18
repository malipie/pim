<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Doctrine\Repository;

use App\Identity\Domain\Entity\Invitation;
use App\Identity\Domain\Repository\InvitationRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
class DoctrineInvitationRepository extends ServiceEntityRepository implements InvitationRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    public function findById(Uuid $id): ?Invitation
    {
        return parent::find($id->toRfc4122());
    }

    public function findByHash(string $tokenHash): ?Invitation
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * @return list<Invitation>
     */
    public function findByTenant(Uuid $tenantId): array
    {
        return array_values($this->findBy(['tenantId' => $tenantId->toRfc4122()]));
    }

    /**
     * @return list<Invitation>
     */
    public function findByEmail(string $email): array
    {
        return array_values($this->findBy(['email' => $email]));
    }

    public function save(Invitation $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(Invitation $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
