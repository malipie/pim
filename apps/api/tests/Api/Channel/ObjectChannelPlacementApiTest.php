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
 * CHC-03 (#1286) — `/api/products/{id}/channel-placements` CRUD.
 */
final class ObjectChannelPlacementApiTest extends ChannelApiTestCase
{
    /**
     * @return array{channelId: string, nodeId: string}
     */
    private function createChannelWithNode(Client $client, string $code): array
    {
        $channel = $client->request('POST', '/api/channels', [
            'json' => ['code' => $code, 'name' => $code, 'locales' => ['pl_PL']],
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

    /**
     * @return list<array<array-key, mixed>>
     */
    private function rows(Client $client, string $productId): array
    {
        $body = $client->request('GET', "/api/products/{$productId}/channel-placements", [
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
    public function listReturnsEveryChannelWithNullPlacementByDefault(): void
    {
        $client = $this->authenticatedClient();
        $this->createChannelWithNode($client, 'allegro');
        $productId = $this->makeProductId();

        $rows = $this->rows($client, $productId);
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
        $placement = $put->toArray()['placement'] ?? null;
        \assert(\is_array($placement));
        self::assertSame($nodeId, $placement['nodeId']);
        self::assertSame('manual', $placement['source']);
        $nodePath = $placement['nodePath'] ?? '';
        \assert(\is_string($nodePath));
        self::assertStringContainsString('Telewizory', $nodePath);

        $rows = $this->rows($client, $productId);
        $rowPlacement = $rows[0]['placement'];
        \assert(\is_array($rowPlacement));
        self::assertSame($nodeId, $rowPlacement['nodeId']);
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

        $rows = $this->rows($client, $productId);
        self::assertNull($rows[0]['placement']);
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
