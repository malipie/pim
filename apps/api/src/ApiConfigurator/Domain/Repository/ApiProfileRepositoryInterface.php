<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Domain\Repository;

use App\ApiConfigurator\Domain\Entity\ApiProfile;
use Symfony\Component\Uid\Uuid;

interface ApiProfileRepositoryInterface
{
    public function save(ApiProfile $profile): void;

    public function remove(ApiProfile $profile): void;

    public function findById(Uuid $id): ?ApiProfile;

    public function findByCode(string $code): ?ApiProfile;

    /**
     * Profiles with a configured `webhookUrl` + `webhookSecret` whose
     * `webhookEvents` JSONB list contains the supplied event type
     * (e.g. `object.created.product`). Used by the delivery
     * subscriber — empty list = no fan-out work.
     *
     * @return list<ApiProfile>
     */
    public function findWebhookSubscribersFor(string $eventType): array;
}
