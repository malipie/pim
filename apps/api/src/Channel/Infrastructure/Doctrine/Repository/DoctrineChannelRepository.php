<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\Doctrine\Repository;

use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Repository\ChannelRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Channel>
 */
class DoctrineChannelRepository extends ServiceEntityRepository implements ChannelRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Channel::class);
    }

    public function findByCode(string $code, Tenant $tenant): ?Channel
    {
        return $this->findOneBy(['code' => $code, 'tenant' => $tenant]);
    }

    public function findById(\Symfony\Component\Uid\Uuid $id): ?Channel
    {
        return parent::find($id->toRfc4122());
    }

    /**
     * @return list<Channel>
     */
    public function findAllForTenant(Tenant $tenant): array
    {
        return array_values($this->findBy(['tenant' => $tenant], ['code' => 'ASC']));
    }

    public function save(Channel $entity): void
    {
        $em = $this->getEntityManager();
        $em->persist($entity);
        $em->flush();
    }

    public function remove(Channel $entity): void
    {
        $em = $this->getEntityManager();
        $em->remove($entity);
        $em->flush();
    }
}
