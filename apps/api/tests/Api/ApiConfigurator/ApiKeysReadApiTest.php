<?php

declare(strict_types=1);

namespace App\Tests\Api\ApiConfigurator;

use App\ApiConfigurator\Application\ApiKeyGenerator;
use App\ApiConfigurator\Domain\Entity\ApiKey;
use App\ApiConfigurator\Domain\Repository\ApiKeyRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * Defence-in-depth: `keyHash` MUST never appear on the wire, regardless
 * of caller permissions. The serializer XML excludes it from every
 * group; this test pins that invariant.
 */
final class ApiKeysReadApiTest extends ApiConfiguratorApiTestCase
{
    #[Test]
    public function getCollectionExcludesKeyHash(): void
    {
        $this->seedKey();
        $client = $this->authenticatedClient();

        $body = $client->request('GET', '/api/api_keys')->toArray();
        $members = $body['member'] ?? $body['hydra:member'] ?? null;
        \assert(\is_array($members));
        self::assertNotEmpty($members);

        foreach ($members as $row) {
            \assert(\is_array($row));
            self::assertArrayNotHasKey('keyHash', $row);
            self::assertArrayHasKey('keyPrefix', $row);
            $prefix = $row['keyPrefix'] ?? '';
            self::assertIsString($prefix);
            self::assertStringStartsWith('pim_', $prefix);
        }
    }

    #[Test]
    public function getItemExcludesKeyHash(): void
    {
        $key = $this->seedKey();
        $client = $this->authenticatedClient();

        $body = $client->request('GET', '/api/api_keys/'.$key->getId()->toRfc4122())->toArray();

        self::assertArrayNotHasKey('keyHash', $body);
        self::assertSame($key->getKeyPrefix(), $body['keyPrefix'] ?? null);
        self::assertSame('demo-key', $body['name'] ?? null);
    }

    private function seedKey(): ApiKey
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $generator = self::getContainer()->get(ApiKeyGenerator::class);
        $generated = $generator->generate();
        $key = new ApiKey(
            keyHash: $generated->keyHash,
            keyPrefix: $generated->keyPrefix,
            name: 'demo-key',
            scopes: [],
        );
        $repo = self::getContainer()->get(ApiKeyRepositoryInterface::class);
        $repo->save($key);

        return $key;
    }
}
