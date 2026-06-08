<?php

declare(strict_types=1);

namespace App\Tests\Api\Channel;

use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * CHC-06 (#1289) — `/api/channels/{channelId}/node-mappings` CRUD.
 */
final class ChannelNodeMappingApiTest extends ChannelApiTestCase
{
    /**
     * @return array{channelId: string, nodeId: string}
     */
    private function createChannelWithNode(Client $client, string $code): array
    {
        $channel = $client->request('POST', '/api/channels', [
            'json' => ['code' => $code, 'name' => $code],
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

        return ['channelId' => $channelId, 'nodeId' => self::extractId($node->toArray())];
    }

    private function makeObjectId(ObjectKind $kind, string $code): string
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $type = self::getContainer()->get(ObjectTypeRepositoryInterface::class)->findBuiltInByKind($kind, $tenant);
        \assert(null !== $type);
        $object = new CatalogObject($type, $code);
        $em->persist($object);
        $em->flush();

        return $object->getId()->toRfc4122();
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    private function mappings(Client $client, string $channelId): array
    {
        $body = $client->request('GET', "/api/channels/{$channelId}/node-mappings", [
            'headers' => ['accept' => 'application/json'],
        ])->toArray();
        $member = $body['member'] ?? [];
        \assert(\is_array($member));
        $out = [];
        foreach ($member as $row) {
            \assert(\is_array($row));
            $out[] = $row;
        }

        return $out;
    }

    #[Test]
    public function putThenGetReturnsMapping(): void
    {
        $client = $this->authenticatedClient();
        ['channelId' => $channelId, 'nodeId' => $nodeId] = $this->createChannelWithNode($client, 'allegro');
        $masterId = $this->makeObjectId(ObjectKind::Category, 'cat_tv');

        $put = $client->request('PUT', "/api/channels/{$channelId}/node-mappings/{$masterId}", [
            'json' => ['nodeIds' => [$nodeId]],
        ]);
        self::assertSame(200, $put->getStatusCode());

        $rows = $this->mappings($client, $channelId);
        self::assertCount(1, $rows);
        self::assertSame($masterId, $rows[0]['masterCategoryId']);
        self::assertSame([$nodeId], $rows[0]['channelNodeIds']);
    }

    #[Test]
    public function putRejectsNonCategoryMaster(): void
    {
        $client = $this->authenticatedClient();
        ['channelId' => $channelId, 'nodeId' => $nodeId] = $this->createChannelWithNode($client, 'allegro');
        $productId = $this->makeObjectId(ObjectKind::Product, 'SKU-NODEMAP');

        $put = $client->request('PUT', "/api/channels/{$channelId}/node-mappings/{$productId}", [
            'json' => ['nodeIds' => [$nodeId]],
        ]);
        self::assertSame(422, $put->getStatusCode());
    }

    #[Test]
    public function putRejectsNodeFromAnotherChannel(): void
    {
        $client = $this->authenticatedClient();
        $a = $this->createChannelWithNode($client, 'allegro');
        $b = $this->createChannelWithNode($client, 'shopify');
        $masterId = $this->makeObjectId(ObjectKind::Category, 'cat_tv');

        $put = $client->request('PUT', "/api/channels/{$a['channelId']}/node-mappings/{$masterId}", [
            'json' => ['nodeIds' => [$b['nodeId']]],
        ]);
        self::assertSame(422, $put->getStatusCode());
    }

    #[Test]
    public function deleteRemovesMapping(): void
    {
        $client = $this->authenticatedClient();
        ['channelId' => $channelId, 'nodeId' => $nodeId] = $this->createChannelWithNode($client, 'allegro');
        $masterId = $this->makeObjectId(ObjectKind::Category, 'cat_tv');

        $client->request('PUT', "/api/channels/{$channelId}/node-mappings/{$masterId}", [
            'json' => ['nodeIds' => [$nodeId]],
        ]);

        $delete = $client->request('DELETE', "/api/channels/{$channelId}/node-mappings/{$masterId}");
        self::assertSame(204, $delete->getStatusCode());
        self::assertCount(0, $this->mappings($client, $channelId));
    }

    #[Test]
    public function clearAllRemovesEveryMappingForChannel(): void
    {
        $client = $this->authenticatedClient();
        ['channelId' => $channelId, 'nodeId' => $nodeId] = $this->createChannelWithNode($client, 'allegro');
        $masterA = $this->makeObjectId(ObjectKind::Category, 'cat_a');
        $masterB = $this->makeObjectId(ObjectKind::Category, 'cat_b');

        $client->request('PUT', "/api/channels/{$channelId}/node-mappings/{$masterA}", ['json' => ['nodeIds' => [$nodeId]]]);
        $client->request('PUT', "/api/channels/{$channelId}/node-mappings/{$masterB}", ['json' => ['nodeIds' => [$nodeId]]]);
        self::assertCount(2, $this->mappings($client, $channelId));

        $clear = $client->request('DELETE', "/api/channels/{$channelId}/node-mappings");
        self::assertSame(200, $clear->getStatusCode());
        self::assertSame(2, $clear->toArray()['deleted'] ?? null);
        self::assertCount(0, $this->mappings($client, $channelId));
    }

    #[Test]
    public function placementCountsReportsProductsPerNode(): void
    {
        $client = $this->authenticatedClient();
        ['channelId' => $channelId, 'nodeId' => $nodeId] = $this->createChannelWithNode($client, 'allegro');
        $productId = $this->makeObjectId(ObjectKind::Product, 'SKU-COUNT');

        // CHC-03 manual placement so the node has one product.
        $place = $client->request('PUT', "/api/products/{$productId}/channel-placements/{$channelId}", [
            'json' => ['nodeId' => $nodeId],
        ]);
        self::assertSame(200, $place->getStatusCode());

        $body = $client->request('GET', "/api/channels/{$channelId}/node-placement-counts", [
            'headers' => ['accept' => 'application/json'],
        ])->toArray();
        $member = $body['member'] ?? [];
        \assert(\is_array($member));
        self::assertCount(1, $member);
        $first = $member[0];
        \assert(\is_array($first));
        self::assertSame($nodeId, $first['nodeId'] ?? null);
        self::assertSame(1, $first['productCount'] ?? null);
    }

    #[Test]
    public function unauthenticatedIsRejected(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/channels/0192ffff-ffff-7fff-8fff-ffffffffffff/node-mappings', [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertSame(401, $response->getStatusCode());
    }
}
