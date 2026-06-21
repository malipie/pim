<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Application\Subscriber;

use App\ApiConfigurator\Application\WebhookDeliveryClient;
use App\ApiConfigurator\Domain\Repository\ApiProfileRepositoryInterface;
use App\Catalog\Application\BulkContext;
use App\Catalog\Contracts\Event\ObjectArchived;
use App\Catalog\Contracts\Event\ObjectAttributesChanged;
use App\Catalog\Contracts\Event\ObjectCreated;
use App\Catalog\Contracts\Event\ObjectEnabledChanged;
use App\Catalog\Contracts\Event\ObjectPublished;
use App\Shared\Domain\DomainEvent;
use DateTimeInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Fans Catalog domain events out to per-profile outbound webhooks.
 *
 * Mirrors {@see \App\Catalog\Application\Subscriber\MercurePublisher}
 * — same event surface, different delivery channel. Each profile with
 * a configured `webhookUrl` + `webhookSecret` and a matching event
 * code in `webhookEvents` receives an HMAC-signed POST.
 *
 * Webhooks are best-effort: client failures are logged inside
 * {@see WebhookDeliveryClient}, never thrown — a stuck integrator
 * MUST NOT block the originating write path. Retry policy via
 * Symfony Messenger transport lands in #93 follow-up.
 */
final readonly class WebhookDeliverySubscriber
{
    public function __construct(
        private ApiProfileRepositoryInterface $profiles,
        private WebhookDeliveryClient $client,
        private BulkContext $bulkContext,
    ) {
    }

    #[AsMessageHandler]
    public function onObjectCreated(ObjectCreated $event): void
    {
        $this->fanOut('object.created.'.$event->kind->value, $event, [
            'objectId' => $event->aggregateId(),
            'kind' => $event->kind->value,
            'code' => $event->code,
        ]);
    }

    #[AsMessageHandler]
    public function onObjectAttributesChanged(ObjectAttributesChanged $event): void
    {
        $this->fanOut('object.attributes_changed', $event, [
            'objectId' => $event->aggregateId(),
            'changedAttributeCodes' => $event->changedAttributeCodes,
        ]);
    }

    #[AsMessageHandler]
    public function onObjectEnabledChanged(ObjectEnabledChanged $event): void
    {
        $this->fanOut('object.enabled_changed', $event, [
            'objectId' => $event->aggregateId(),
            'enabled' => $event->enabled,
        ]);
    }

    #[AsMessageHandler]
    public function onObjectPublished(ObjectPublished $event): void
    {
        $this->fanOut('object.published', $event, [
            'objectId' => $event->aggregateId(),
        ]);
    }

    #[AsMessageHandler]
    public function onObjectArchived(ObjectArchived $event): void
    {
        $this->fanOut('object.archived', $event, [
            'objectId' => $event->aggregateId(),
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function fanOut(string $eventType, DomainEvent $event, array $data): void
    {
        // Bulk flows (import, bulk-edit/-delete) emit one event per object on the
        // SYNC bus; one webhook POST each would pile up HTTP responses and starve
        // the 256 MiB worker. Per-object webhooks during a bulk run are both
        // impractical (thousands of inline POSTs) and rarely what an integrator
        // wants — a batch/summary delivery is the right shape (follow-up). Skip
        // here, mirroring MercurePublisher's BulkContext opt-out.
        if ($this->bulkContext->isBulk()) {
            return;
        }

        $subscribers = $this->profiles->findWebhookSubscribersFor($eventType);
        if ([] === $subscribers) {
            return;
        }

        foreach ($subscribers as $profile) {
            $url = $profile->getWebhookUrl();
            $secret = $profile->getWebhookSecret();
            if (null === $url || null === $secret) {
                continue;
            }
            $this->client->deliver($url, $secret, [
                'event' => $eventType,
                'occurredOn' => $event->occurredOn()->format(DateTimeInterface::RFC3339_EXTENDED),
                'profileCode' => $profile->getCode(),
                'data' => $data,
            ]);
        }
    }
}
