<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Application\Subscriber\MercurePublisher;
use App\Catalog\Contracts\Event\ObjectAttributesChanged;
use App\Catalog\Contracts\Event\ObjectCreated;
use App\Catalog\Contracts\Event\ObjectEnabledChanged;
use App\Catalog\Domain\ObjectKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Uid\Uuid;

/**
 * Unit coverage for MercurePublisher topic + payload contract (#47 / 0.4.7).
 *
 * Publisher emits domain events on two topics:
 *   - row-specific `<base>/objects/<id>` so an admin editing a single
 *     row subscribes once and gets every change to that row;
 *   - broadcast `<base>/objects` so a list view picks up creations /
 *     deletions across the whole catalog with one connection.
 *
 * Payload shape: `{type, occurredOn, data}` — `type` is the per-event
 * dot-cased name (kind-aware for create), `data` is a small dict the
 * subscriber can act on without re-fetching the row.
 */
final class MercurePublisherTest extends TestCase
{
    /**
     * @var list<Update>
     */
    private array $captured = [];

    #[Test]
    public function objectCreatedPushesPerRowAndBroadcastTopics(): void
    {
        $publisher = $this->makePublisher();
        $event = new ObjectCreated(
            objectId: Uuid::fromString('019ddb19-a0f7-750a-a9cb-a6a6447ccb26'),
            kind: ObjectKind::Product,
            code: 'SKU-1',
            tenantId: Uuid::v7(),
        );

        $publisher->onObjectCreated($event);

        self::assertCount(1, $this->captured);
        $update = $this->captured[0];
        self::assertSame([
            'https://pim.localhost/objects/019ddb19-a0f7-750a-a9cb-a6a6447ccb26',
            'https://pim.localhost/objects',
        ], $update->getTopics());

        $payload = json_decode($update->getData(), true);
        \assert(\is_array($payload));
        self::assertSame('object.created.product', $payload['type'] ?? null);
        $data = $payload['data'] ?? null;
        \assert(\is_array($data));
        self::assertSame('019ddb19-a0f7-750a-a9cb-a6a6447ccb26', $data['objectId'] ?? null);
        self::assertSame('product', $data['kind'] ?? null);
        self::assertSame('SKU-1', $data['code'] ?? null);
    }

    #[Test]
    public function objectAttributesChangedCarriesChangedAttributeCodes(): void
    {
        $publisher = $this->makePublisher();
        $event = new ObjectAttributesChanged(
            objectId: Uuid::fromString('019ddb19-a0f7-750a-a9cb-a6a6447ccb27'),
            tenantId: Uuid::v7(),
            changedAttributeCodes: ['color', 'weight'],
        );

        $publisher->onObjectAttributesChanged($event);

        self::assertCount(1, $this->captured);
        $payload = json_decode($this->captured[0]->getData(), true);
        \assert(\is_array($payload));
        self::assertSame('object.attributes_changed', $payload['type'] ?? null);
        $data = $payload['data'] ?? null;
        \assert(\is_array($data));
        self::assertSame(['color', 'weight'], $data['changedAttributeCodes'] ?? null);
    }

    #[Test]
    public function objectEnabledChangedCarriesEnabledFlag(): void
    {
        $publisher = $this->makePublisher();
        $event = new ObjectEnabledChanged(
            objectId: Uuid::fromString('019ddb19-a0f7-750a-a9cb-a6a6447ccb28'),
            tenantId: Uuid::v7(),
            enabled: false,
        );

        $publisher->onObjectEnabledChanged($event);

        self::assertCount(1, $this->captured);
        $payload = json_decode($this->captured[0]->getData(), true);
        \assert(\is_array($payload));
        self::assertSame('object.enabled_changed', $payload['type'] ?? null);
        $data = $payload['data'] ?? null;
        \assert(\is_array($data));
        self::assertFalse($data['enabled'] ?? true);
    }

    #[Test]
    public function topicForKindHelperBuildsKindFilteredTopic(): void
    {
        self::assertSame(
            'https://pim.localhost/objects/kind/product',
            MercurePublisher::topicForKind(ObjectKind::Product),
        );
        self::assertSame(
            'https://pim.example.com/objects/kind/category',
            MercurePublisher::topicForKind(ObjectKind::Category, 'https://pim.example.com'),
        );
    }

    private function makePublisher(): MercurePublisher
    {
        $this->captured = [];

        $hub = new MockHub(
            url: 'http://localhost/.well-known/mercure',
            jwtProvider: new StaticTokenProvider('test-jwt'),
            publisher: function (Update $update): string {
                $this->captured[] = $update;

                return 'urn:uuid:'.bin2hex(random_bytes(8));
            },
        );

        return new MercurePublisher($hub);
    }
}
