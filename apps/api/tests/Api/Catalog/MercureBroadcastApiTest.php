<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\ObjectKind;
use App\Tests\Support\InMemoryMercureHub;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * Integration coverage for #47 (0.4.7): the Catalog write path
 * publishes a Mercure update through the real flush → dispatch
 * pipeline. The in-memory hub from `tests/Support/` captures every
 * `Update` so we can assert topics + payload without a network leg.
 */
final class MercureBroadcastApiTest extends CatalogApiTestCase
{
    #[Test]
    public function postProductPublishesObjectCreatedToHubOnBothTopics(): void
    {
        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'MERCURE-001',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();

        $newId = $created['id'] ?? null;
        \assert(\is_string($newId));

        // Hub is fetched after the kernel has handled the request so we
        // observe the same singleton the dispatcher and message handler
        // saw — the ApiTestCase shares the booted kernel between the
        // request and `getContainer()`.
        $hub = self::getContainer()->get(InMemoryMercureHub::class);
        \assert($hub instanceof InMemoryMercureHub);
        $updates = $hub->getCapturedUpdates();
        self::assertNotEmpty($updates, 'POST /api/products must publish at least one Mercure update.');

        // First update is the ObjectCreated event — topic shape should
        // include the per-row IRI + the broadcast IRI.
        $first = $updates[0];
        $topics = $first->getTopics();
        self::assertContains('https://pim.localhost/objects/'.$newId, $topics);
        self::assertContains('https://pim.localhost/objects', $topics);

        $payload = json_decode($first->getData(), true);
        \assert(\is_array($payload));
        self::assertSame('object.created.product', $payload['type'] ?? null);
    }

    #[Test]
    public function deleteDoesNotPublishObjectArchivedWhenStatusUntouched(): void
    {
        $hub = self::getContainer()->get(InMemoryMercureHub::class);
        \assert($hub instanceof InMemoryMercureHub);
        $hub->reset();

        $client = $this->authenticatedClient();
        $created = $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'MERCURE-DEL',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ])->toArray();
        $id = $created['id'] ?? null;
        \assert(\is_string($id));

        $hub->reset();
        $client->request('DELETE', '/api/products/'.$id);
        self::assertResponseStatusCodeSame(204);

        // The aggregate has no `Deleted` event today (#41); a row removal
        // emits no Mercure update. This test pins the contract — when
        // we add ObjectDeleted later, this assertion needs flipping.
        self::assertSame([], $hub->getCapturedUpdates(), 'DELETE without ObjectDeleted event must not publish.');
    }
}
