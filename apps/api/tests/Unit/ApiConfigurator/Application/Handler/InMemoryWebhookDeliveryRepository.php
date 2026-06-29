<?php

declare(strict_types=1);

namespace App\Tests\Unit\ApiConfigurator\Application\Handler;

use App\ApiConfigurator\Domain\Entity\WebhookDelivery;
use App\ApiConfigurator\Domain\Repository\WebhookDeliveryRepositoryInterface;
use Symfony\Component\Uid\Uuid;

/**
 * In-memory {@see WebhookDeliveryRepositoryInterface} for the delivery-handler
 * unit tests.
 */
final class InMemoryWebhookDeliveryRepository implements WebhookDeliveryRepositoryInterface
{
    /** @var array<string, WebhookDelivery> */
    private array $store = [];

    /** @var list<WebhookDelivery> */
    public array $saved = [];

    public function save(WebhookDelivery $delivery): void
    {
        $this->store[$delivery->getId()->toRfc4122()] = $delivery;
        $this->saved[] = $delivery;
    }

    public function findById(Uuid $id): ?WebhookDelivery
    {
        return $this->store[$id->toRfc4122()] ?? null;
    }

    /**
     * @return list<WebhookDelivery>
     */
    public function findByProfile(Uuid $profileId, int $limit = 50): array
    {
        return array_values(
            array_filter(
                $this->store,
                static fn (WebhookDelivery $d): bool => $d->getProfileId()->equals($profileId),
            ),
        );
    }
}
