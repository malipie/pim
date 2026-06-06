<?php

declare(strict_types=1);

namespace App\Tests\Api\Channel;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * CHC-03 (#1286) — `/api/products/{id}/channel-placements` CRUD.
 */
final class ObjectChannelPlacementApiTest extends ChannelApiTestCase
{
    private function createChannelWithNode(\ApiPlatform\Symfony\Bundle\Test\Client $client, string $code): array
    {
        $channel = $client->request('POST', '/api/channels', [
            'json' => ['code' => $code, 'label' => ['pl' => $code], 'locales' => ['pl_PL']],
        ]);
        self::assertSame(201, $channel->getStatusCode());
        $channelId = self::extractId($channel->toArray());

        $root = $client->request('POST', "/api/channels/{$channelId}/navigation-tree", [
            'json' => ['label' => ['pl' => 'Korzeń']],
        ]);
        $rootId = self::extractId($root->toArray());

        $node = $client->request('POST', "/api/channels/{$channelId}/navigation-tree/nodes", [
            'json' => ['parentId' => $rootId, 'code' => 'telewizory', 'label' => ['pl' => 'Telewizory']],
        ]);
        $nodeId = self::extractId($node->toArray());

        return ['channelId' => $channelId, 'nodeId' => $nodeId];
    }

    private function makeProductId(): string
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $productType = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $productType);

        $product = new CatalogObject($productType, 'SKU-CHC03');
        $em->persist($product);
        $em->flush();

        return $product->getId()->toRfc4122();
    }

    #[Test]
    public function listReturnsEveryChannelWithNullPlacementByDefault(): void
    {
        $client = $this->authenticatedClient();
        $this->createChannelWithNode($client, 'allegro');
        $productId = $this->makeProductId();

        $response = $client->request('GET', "/api/products/{$productId}/channel-placements", [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertSame(200, $response->getStatusCode());
        $rows = $response->toArray()['member'];
        self::assertCount(1, $rows);
        self::assertSame('allegro', $rows[0]['channelCode']);
        self::assertNull($rows[0]['placement']);
    }

    #[Test]
    public function putAssignsPlacementThenGetReflectsIt(): void
    {
        $client = $this->authenticatedClient();
        ['channelId' => $channelId, 'nodeId' => $nodeId] = $this->createChannelWithNode($client, 'allegro');
        $productId = $this->makeProductId();

        $put = $client->request('PUT', "/api/products/{$productId}/channel-placements/{$channelId}", [
            'json' => ['nodeId' => $nodeId],
        ]);
        self::assertSame(200, $put->getStatusCode());
        $body = $put->toArray();
        self::assertSame($nodeId, $body['placement']['nodeId']);
        self::assertSame('manual', $body['placement']['source']);
        self::assertStringContainsString('Telewizory', $body['placement']['nodePath']);

        $get = $client->request('GET', "/api/products/{$productId}/channel-placements", [
            'headers' => ['accept' => 'application/json'],
        ]);
        $row = $get->toArray()['member'][0];
        self::assertNotNull($row['placement']);
        self::assertSame($nodeId, $row['placement']['nodeId']);
    }

    #[Test]
    public function putRejectsNodeFromAnotherChannel(): void
    {
        $client = $this->authenticatedClient();
        $a = $this->createChannelWithNode($client, 'allegro');
        $b = $this->createChannelWithNode($client, 'shopify');
        $productId = $this->makeProductId();

        // Channel A placement pointing at channel B's node → 422.
        $put = $client->request('PUT', "/api/products/{$productId}/channel-placements/{$a['channelId']}", [
            'json' => ['nodeId' => $b['nodeId']],
        ]);
        self::assertSame(422, $put->getStatusCode());
    }

    #[Test]
    public function deleteRemovesPlacement(): void
    {
        $client = $this->authenticatedClient();
        ['channelId' => $channelId, 'nodeId' => $nodeId] = $this->createChannelWithNode($client, 'allegro');
        $productId = $this->makeProductId();

        $client->request('PUT', "/api/products/{$productId}/channel-placements/{$channelId}", [
            'json' => ['nodeId' => $nodeId],
        ]);

        $delete = $client->request('DELETE', "/api/products/{$productId}/channel-placements/{$channelId}");
        self::assertSame(204, $delete->getStatusCode());

        $get = $client->request('GET', "/api/products/{$productId}/channel-placements", [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertNull($get->toArray()['member'][0]['placement']);
    }

    #[Test]
    public function unauthenticatedIsRejected(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/products/0192ffff-ffff-7fff-8fff-ffffffffffff/channel-placements', [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertSame(401, $response->getStatusCode());
    }
}
