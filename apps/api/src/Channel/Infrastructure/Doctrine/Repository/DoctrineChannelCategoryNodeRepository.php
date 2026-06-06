<?php

declare(strict_types=1);

namespace App\Channel\Infrastructure\Doctrine\Repository;

use App\Channel\Domain\Entity\Channel;
use App\Channel\Domain\Entity\ChannelCategoryNode;
use App\Channel\Domain\Repository\ChannelCategoryNodeRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ChannelCategoryNode>
 */
class DoctrineChannelCategoryNodeRepository extends ServiceEntityRepository implements ChannelCategoryNodeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChannelCategoryNode::class);
    }

    public function findById(Uuid $id): ?ChannelCategoryNode
    {
        return parent::find($id->toRfc4122());
    }

    public function findRootForChannel(Channel $channel): ?ChannelCategoryNode
    {
        return $this->findOneBy(['channel' => $channel, 'parent' => null]);
    }

    /**
     * @return list<ChannelCategoryNode>
     */
    public function findAllForChannel(Channel $channel): array
    {
        return $this->findBy(['channel' => $channel], ['path' => 'ASC']);
    }

    public function save(ChannelCategoryNode $node): void
    {
        $em = $this->getEntityManager();
        $em->persist($node);
        $em->flush();
    }

    public function remove(ChannelCategoryNode $node): void
    {
        $em = $this->getEntityManager();
        $em->remove($node);
        $em->flush();
    }
}
