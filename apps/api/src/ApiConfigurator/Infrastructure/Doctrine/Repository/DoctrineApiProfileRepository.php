<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Infrastructure\Doctrine\Repository;

use App\ApiConfigurator\Domain\Entity\ApiProfile;
use App\ApiConfigurator\Domain\Repository\ApiProfileRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * @extends ServiceEntityRepository<ApiProfile>
 */
class DoctrineApiProfileRepository extends ServiceEntityRepository implements ApiProfileRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApiProfile::class);
    }

    public function save(ApiProfile $profile): void
    {
        $em = $this->getEntityManager();
        $em->persist($profile);
        $em->flush();
    }

    public function remove(ApiProfile $profile): void
    {
        $em = $this->getEntityManager();
        $em->remove($profile);
        $em->flush();
    }

    public function findById(Uuid $id): ?ApiProfile
    {
        return parent::find($id->toRfc4122());
    }

    public function findByCode(string $code): ?ApiProfile
    {
        // The TenantFilter narrows the row set to the current tenant; the
        // unique index `(tenant_id, code)` from the migration guarantees
        // at most one row.
        return $this->findOneBy(['code' => $code]);
    }

    public function findWebhookSubscribersFor(string $eventType): array
    {
        // JSONB containment via custom DQL (`JSONB_CONTAINS` from #43)
        // — `webhook_events @> ["<event>"]::jsonb`. Only rows with
        // a non-null url + secret matter; empty configurations are
        // skipped here so the subscriber never fan-outs to dead URLs.
        $qb = $this->createQueryBuilder('p')
            ->where('p.webhookUrl IS NOT NULL')
            ->andWhere('p.webhookSecret IS NOT NULL')
            ->andWhere('JSONB_CONTAINS(p.webhookEvents, :payload) = true')
            ->setParameter('payload', json_encode([$eventType], JSON_THROW_ON_ERROR));

        /** @var list<ApiProfile> $rows */
        $rows = $qb->getQuery()->getResult();

        return $rows;
    }
}
