<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Infrastructure\Doctrine\Repository;

use App\ApiConfigurator\Domain\Entity\WebhookDelivery;
use App\ApiConfigurator\Domain\Repository\WebhookDeliveryRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<WebhookDelivery>
 */
class DoctrineWebhookDeliveryRepository extends ServiceEntityRepository implements WebhookDeliveryRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebhookDelivery::class);
    }

    public function save(WebhookDelivery $delivery): void
    {
        $em = $this->getEntityManager();
        $em->persist($delivery);
        $em->flush();
    }

    public function findById(Uuid $id): ?WebhookDelivery
    {
        return parent::find($id->toRfc4122());
    }

    /**
     * @return list<WebhookDelivery>
     */
    public function findByProfile(Uuid $profileId, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.profileId = :profileId')
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('profileId', $profileId->toRfc4122());

        /** @var list<WebhookDelivery> $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
