<?php

declare(strict_types=1);

namespace App\Tests\Api\Channel;

use PHPUnit\Framework\Attributes\Test;

/**
 * CHC-01 (#1284) — `/api/channels/{channelId}/navigation-tree` CRUD.
 */
final class ChannelNavigationTreeApiTest extends ChannelApiTestCase
{
    private function createChannel(\ApiPlatform\Symfony\Bundle\Test\Client $client, string $code = 'allegro'): string
    {
        $created = $client->request('POST', '/api/channels', [
            'json' => [
                'code' => $code,
                'label' => ['pl' => 'Allegro', 'en' => 'Allegro'],
                'locales' => ['pl_PL'],
            ],
        ]);
        self::assertSame(201, $created->getStatusCode());

        return self::extractId($created->toArray());
    }

    #[Test]
    public function createRootAddNodesThenGetTree(): void
    {
        $client = $this->authenticatedClient();
        $channelId = $this->createChannel($client);

        $root = $client->request('POST', "/api/channels/{$channelId}/navigation-tree", [
            'json' => ['code' => 'root', 'label' => ['pl' => 'Korzeń']],
        ]);
        self::assertSame(201, $root->getStatusCode());
        $rootBody = $root->toArray();
        $rootId = self::extractId($rootBody);
        self::assertNull($rootBody['parentId']);
        self::assertNotEmpty($rootBody['path']);

        $child = $client->request('POST', "/api/channels/{$channelId}/navigation-tree/nodes", [
            'json' => [
                'parentId' => $rootId,
                'code' => 'telewizory',
                'label' => ['pl' => 'Telewizory'],
                'externalCode' => '123456',
            ],
        ]);
        self::assertSame(201, $child->getStatusCode());
        $childBody = $child->toArray();
        self::assertSame($rootId, $childBody['parentId']);
        self::assertSame('123456', $childBody['externalCode']);

        $tree = $client->request('GET', "/api/channels/{$channelId}/navigation-tree", [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertSame(200, $tree->getStatusCode());
        $nodes = $tree->toArray();
        self::assertCount(2, $nodes);
    }

    #[Test]
    public function getTreeRequiresExistingChannel(): void
    {
        $client = $this->authenticatedClient();
        $missing = '0192ffff-ffff-7fff-8fff-ffffffffffff';

        $response = $client->request('GET', "/api/channels/{$missing}/navigation-tree", [
            'headers' => ['accept' => 'application/json'],
        ]);

        self::assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function rootCannotBeCreatedTwice(): void
    {
        $client = $this->authenticatedClient();
        $channelId = $this->createChannel($client);

        $first = $client->request('POST', "/api/channels/{$channelId}/navigation-tree", [
            'json' => ['label' => ['pl' => 'Korzeń']],
        ]);
        self::assertSame(201, $first->getStatusCode());

        $second = $client->request('POST', "/api/channels/{$channelId}/navigation-tree", [
            'json' => ['label' => ['pl' => 'Korzeń 2']],
        ]);
        self::assertSame(409, $second->getStatusCode());
    }

    #[Test]
    public function addingNodeUnderForeignParentIsRejected(): void
    {
        $client = $this->authenticatedClient();
        $channelA = $this->createChannel($client, 'allegro');
        $channelB = $this->createChannel($client, 'shopify');

        $rootA = $client->request('POST', "/api/channels/{$channelA}/navigation-tree", [
            'json' => ['label' => ['pl' => 'Korzeń A']],
        ]);
        $rootAId = self::extractId($rootA->toArray());

        // Try to attach a node to channel B under channel A's root → 422.
        $rejected = $client->request('POST', "/api/channels/{$channelB}/navigation-tree/nodes", [
            'json' => [
                'parentId' => $rootAId,
                'code' => 'x',
                'label' => ['pl' => 'X'],
            ],
        ]);
        self::assertSame(422, $rejected->getStatusCode());
    }

    #[Test]
    public function patchUpdatesLabelAndExternalCode(): void
    {
        $client = $this->authenticatedClient();
        $channelId = $this->createChannel($client);

        $root = $client->request('POST', "/api/channels/{$channelId}/navigation-tree", [
            'json' => ['label' => ['pl' => 'Korzeń']],
        ]);
        $rootId = self::extractId($root->toArray());

        $patched = $client->request('PATCH', "/api/channels/{$channelId}/navigation-tree/nodes/{$rootId}", [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'json' => ['label' => ['pl' => 'Nowy korzeń'], 'externalCode' => '999'],
        ]);
        self::assertSame(200, $patched->getStatusCode());
        $body = $patched->toArray();
        self::assertSame(['pl' => 'Nowy korzeń'], $body['label']);
        self::assertSame('999', $body['externalCode']);
    }

    #[Test]
    public function deleteRootCascadesAndClearsChannelRoot(): void
    {
        $client = $this->authenticatedClient();
        $channelId = $this->createChannel($client);

        $root = $client->request('POST', "/api/channels/{$channelId}/navigation-tree", [
            'json' => ['label' => ['pl' => 'Korzeń']],
        ]);
        $rootId = self::extractId($root->toArray());

        $client->request('POST', "/api/channels/{$channelId}/navigation-tree/nodes", [
            'json' => ['parentId' => $rootId, 'code' => 'child', 'label' => ['pl' => 'Dziecko']],
        ]);

        $deleted = $client->request('DELETE', "/api/channels/{$channelId}/navigation-tree/nodes/{$rootId}");
        self::assertSame(204, $deleted->getStatusCode());

        $tree = $client->request('GET', "/api/channels/{$channelId}/navigation-tree", [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertCount(0, $tree->toArray());

        // Channel root pointer is cleared.
        $channel = $client->request('GET', "/api/channels/{$channelId}", [
            'headers' => ['accept' => 'application/json'],
        ]);
        self::assertNull($channel->toArray()['categoryTreeRootId'] ?? null);
    }

    #[Test]
    public function moveReparentsNodeAndRewritesDescendantPaths(): void
    {
        $client = $this->authenticatedClient();
        $channelId = $this->createChannel($client);
        $rootId = self::extractId($client->request('POST', "/api/channels/{$channelId}/navigation-tree", [
            'json' => ['label' => ['pl' => 'Root']],
        ])->toArray());
        $aId = $this->addNode($client, $channelId, $rootId, 'A');
        $a1Id = $this->addNode($client, $channelId, $aId, 'A1');
        $bId = $this->addNode($client, $channelId, $rootId, 'B');

        $moved = $client->request('PATCH', "/api/channels/{$channelId}/navigation-tree/nodes/{$aId}/move", [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'json' => ['newParentId' => $bId],
        ]);
        self::assertSame(200, $moved->getStatusCode());
        self::assertSame($bId, $moved->toArray()['parentId'] ?? null);

        $paths = $this->pathsById($client, $channelId);
        self::assertStringStartsWith($paths[$bId].'.', $paths[$aId], 'A now sits under B');
        self::assertStringStartsWith($paths[$aId].'.', $paths[$a1Id], 'A1 follows A (descendant rewrite)');
    }

    #[Test]
    public function moveIntoOwnSubtreeIsRejected(): void
    {
        $client = $this->authenticatedClient();
        $channelId = $this->createChannel($client);
        $rootId = self::extractId($client->request('POST', "/api/channels/{$channelId}/navigation-tree", [
            'json' => ['label' => ['pl' => 'Root']],
        ])->toArray());
        $aId = $this->addNode($client, $channelId, $rootId, 'A');
        $a1Id = $this->addNode($client, $channelId, $aId, 'A1');

        $rejected = $client->request('PATCH', "/api/channels/{$channelId}/navigation-tree/nodes/{$aId}/move", [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'json' => ['newParentId' => $a1Id],
        ]);
        self::assertSame(422, $rejected->getStatusCode());
    }

    #[Test]
    public function moveToForeignChannelParentIsRejected(): void
    {
        $client = $this->authenticatedClient();
        $channelA = $this->createChannel($client, 'allegro');
        $channelB = $this->createChannel($client, 'shopify');
        $rootA = self::extractId($client->request('POST', "/api/channels/{$channelA}/navigation-tree", [
            'json' => ['label' => ['pl' => 'Root A']],
        ])->toArray());
        $aId = $this->addNode($client, $channelA, $rootA, 'A');
        $rootB = self::extractId($client->request('POST', "/api/channels/{$channelB}/navigation-tree", [
            'json' => ['label' => ['pl' => 'Root B']],
        ])->toArray());

        $rejected = $client->request('PATCH', "/api/channels/{$channelA}/navigation-tree/nodes/{$aId}/move", [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'json' => ['newParentId' => $rootB],
        ]);
        self::assertSame(422, $rejected->getStatusCode());
    }

    #[Test]
    public function addNodeWithoutCodeGeneratesOne(): void
    {
        $client = $this->authenticatedClient();
        $channelId = $this->createChannel($client);
        $rootId = self::extractId($client->request('POST', "/api/channels/{$channelId}/navigation-tree", [
            'json' => ['label' => ['pl' => 'Root']],
        ])->toArray());

        $node = $client->request('POST', "/api/channels/{$channelId}/navigation-tree/nodes", [
            'json' => ['parentId' => $rootId, 'label' => ['pl' => 'Bez kodu']],
        ]);
        self::assertSame(201, $node->getStatusCode());
        $code = $node->toArray()['code'] ?? null;
        self::assertIsString($code);
        self::assertNotSame('', $code);
    }

    private function addNode(\ApiPlatform\Symfony\Bundle\Test\Client $client, string $channelId, string $parentId, string $name): string
    {
        $node = $client->request('POST', "/api/channels/{$channelId}/navigation-tree/nodes", [
            'json' => ['parentId' => $parentId, 'label' => ['pl' => $name]],
        ]);
        self::assertSame(201, $node->getStatusCode());

        return self::extractId($node->toArray());
    }

    /**
     * @return array<string, string> node id → ltree path
     */
    private function pathsById(\ApiPlatform\Symfony\Bundle\Test\Client $client, string $channelId): array
    {
        $nodes = $client->request('GET', "/api/channels/{$channelId}/navigation-tree", [
            'headers' => ['accept' => 'application/json'],
        ])->toArray();

        $paths = [];
        foreach ($nodes as $node) {
            \assert(\is_array($node));
            $id = $node['id'] ?? null;
            $path = $node['path'] ?? null;
            \assert(\is_string($id) && \is_string($path));
            $paths[$id] = $path;
        }

        return $paths;
    }

    #[Test]
    public function unauthenticatedAccessIsRejected(): void
    {
        $client = static::createClient();
        $response = $client->request('GET', '/api/channels/0192ffff-ffff-7fff-8fff-ffffffffffff/navigation-tree', [
            'headers' => ['accept' => 'application/json'],
        ]);

        self::assertSame(401, $response->getStatusCode());
    }
}
