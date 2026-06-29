<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Domain\Repository;

use App\ApiConfigurator\Domain\Entity\WebhookDelivery;
use Symfony\Component\Uid\Uuid;

interface WebhookDeliveryRepositoryInterface
{
    public function save(WebhookDelivery $delivery): void;

    public function findById(Uuid $id): ?WebhookDelivery;

    /**
     * Most recent deliveries for a profile (history view).
     *
     * @return list<WebhookDelivery>
     */
    public function findByProfile(Uuid $profileId, int $limit = 50): array;
}
