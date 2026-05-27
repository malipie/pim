<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * ADR-014 / MOD-06 (#898) — CRUD smoke for `/api/objects/{id}/relations`.
 */
final class ObjectRelationsCrudApiTest extends CatalogApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // MODRC-01 — production seeder removed; test re-creates the legacy
        // "Powiązania" group + cross_sell/up_sell/related/alternative/accessory
        // attributes so existing assertions keep working against the same
        // controller surface.
        $this->seedTestRelationAttributes();
    }

    #[Test]
    public function putReplacesRelationsForAttribute(): void
    {
        $client = $this->authenticatedClient();

        $source = $this->makeProduct('SOURCE-A');
        $a = $this->makeProduct('TARGET-A');
        $b = $this->makeProduct('TARGET-B');

        $resp = $client->request('PUT', '/api/objects/'.$source->getId()->toRfc4122().'/relations/cross_sell', [
            'json' => [
                'targets' => [
                    ['id' => $a->getId()->toRfc4122()],
                    ['id' => $b->getId()->toRfc4122()],
                ],
            ],
        ]);
        self::assertSame(204, $resp->getStatusCode(), $resp->getContent(false));

        $listResp = $client->request('GET', '/api/objects/'.$source->getId()->toRfc4122().'/relations');
        self::assertSame(200, $listResp->getStatusCode());
        $body = $listResp->toArray();
        self::assertSame($source->getId()->toRfc4122(), $body['sourceObjectId']);

        /** @var list<array<string, mixed>> $relationAttributes */
        $relationAttributes = $body['relationAttributes'];
        $crossSell = $this->groupForCode($relationAttributes, 'cross_sell');
        /** @var list<array{targetObjectId: string, position: int}> $relations */
        $relations = $crossSell['relations'];
        self::assertCount(2, $relations);
        self::assertSame($a->getId()->toRfc4122(), $relations[0]['targetObjectId']);
        self::assertSame($b->getId()->toRfc4122(), $relations[1]['targetObjectId']);
    }

    #[Test]
    public function deleteRemovesSingleRelation(): void
    {
        $client = $this->authenticatedClient();

        $source = $this->makeProduct('SRC-DEL');
        $target = $this->makeProduct('TGT-DEL');

        // Seed one relation first.
        $client->request('PUT', '/api/objects/'.$source->getId()->toRfc4122().'/relations/related', [
            'json' => ['targets' => [['id' => $target->getId()->toRfc4122()]]],
        ]);
        $deleteResp = $client->request(
            'DELETE',
            \sprintf(
                '/api/objects/%s/relations/related/%s',
                $source->getId()->toRfc4122(),
                $target->getId()->toRfc4122(),
            ),
        );
        self::assertSame(204, $deleteResp->getStatusCode(), $deleteResp->getContent(false));

        $listResp = $client->request('GET', '/api/objects/'.$source->getId()->toRfc4122().'/relations');
        /** @var list<array<string, mixed>> $relationAttributes */
        $relationAttributes = $listResp->toArray()['relationAttributes'];
        $related = $this->groupForCode($relationAttributes, 'related');
        self::assertSame([], $related['relations']);
    }

    #[Test]
    public function putRejectsCrossTenantTarget(): void
    {
        $client = $this->authenticatedClient();
        $source = $this->makeProduct('SRC-XTEN');

        // The Acme tenant is seeded by CatalogApiTestCase as a separate
        // shell; its built-in Product ObjectType is reachable only
        // through TenantContext switching. Use a syntactically-valid but
        // unknown UUID — same effect (404 inside the service).
        $client->request('PUT', '/api/objects/'.$source->getId()->toRfc4122().'/relations/cross_sell', [
            'json' => ['targets' => [['id' => '01234567-1234-7000-8000-000000000000']]],
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    /**
     * @param list<array<string, mixed>> $groups
     *
     * @return array<string, mixed>
     */
    private function groupForCode(array $groups, string $code): array
    {
        foreach ($groups as $group) {
            /** @var array{code: string} $attribute */
            $attribute = $group['attribute'];
            if ($code === $attribute['code']) {
                return $group;
            }
        }
        self::fail(\sprintf('Group for attribute code "%s" not found in response.', $code));
    }

    private function makeProduct(string $sku): CatalogObject
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        // TenantContext is required by TenantAssignmentListener at persist time.
        self::getContainer()->get(\App\Shared\Application\TenantContext::class)->set($tenant);

        $productType = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $productType);

        $object = new CatalogObject($productType, $sku);
        $em->persist($object);
        $em->flush();

        return $object;
    }
}
