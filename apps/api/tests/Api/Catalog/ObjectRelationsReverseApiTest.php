<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Application\BuiltInProductRelationAttributesSeeder;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;

/**
 * ADR-014 / MOD-07 (#899) — reverse view of `object_relations`.
 */
final class ObjectRelationsReverseApiTest extends CatalogApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(BuiltInProductRelationAttributesSeeder::class)->seed($tenant);
    }

    #[Test]
    public function reverseListsSourcesGroupedByObjectTypeAndAttribute(): void
    {
        $client = $this->authenticatedClient();
        $target = $this->makeProduct('TGT-REV');
        $sourceA = $this->makeProduct('SRC-REV-A');
        $sourceB = $this->makeProduct('SRC-REV-B');

        // Two products cross-sell the same target.
        $client->request('PUT', '/api/objects/'.$sourceA->getId()->toRfc4122().'/relations/cross_sell', [
            'json' => ['targets' => [['id' => $target->getId()->toRfc4122()]]],
        ]);
        $client->request('PUT', '/api/objects/'.$sourceB->getId()->toRfc4122().'/relations/cross_sell', [
            'json' => ['targets' => [['id' => $target->getId()->toRfc4122()]]],
        ]);

        $response = $client->request('GET', '/api/objects/'.$target->getId()->toRfc4122().'/relations/reverse');
        self::assertSame(200, $response->getStatusCode(), $response->getContent(false));
        $body = $response->toArray();
        self::assertSame($target->getId()->toRfc4122(), $body['targetObjectId']);

        /** @var list<array{attribute: array<string,mixed>, sources: list<array<string,mixed>>}> $reverse */
        $reverse = $body['reverseRelations'];
        self::assertCount(1, $reverse, 'one group (Product/cross_sell)');
        self::assertSame('cross_sell', $reverse[0]['attribute']['code']);
        self::assertCount(2, $reverse[0]['sources']);

        $sourceCodes = [];
        foreach ($reverse[0]['sources'] as $s) {
            $code = $s['code'];
            \assert(\is_string($code));
            $sourceCodes[] = $code;
        }
        sort($sourceCodes);
        self::assertSame(['SRC-REV-A', 'SRC-REV-B'], $sourceCodes);
    }

    #[Test]
    public function reverseReturnsEmptyEnvelopeWhenNoIncomingRelations(): void
    {
        $client = $this->authenticatedClient();
        $orphan = $this->makeProduct('NO-INBOUND');

        $response = $client->request('GET', '/api/objects/'.$orphan->getId()->toRfc4122().'/relations/reverse');
        self::assertSame(200, $response->getStatusCode());
        $body = $response->toArray();
        self::assertSame($orphan->getId()->toRfc4122(), $body['targetObjectId']);
        self::assertSame([], $body['reverseRelations']);
    }

    #[Test]
    public function reverseCountReturnsZeroForOrphan(): void
    {
        $client = $this->authenticatedClient();
        $orphan = $this->makeProduct('REV-COUNT-EMPTY');

        $body = $client->request(
            'GET',
            '/api/objects/'.$orphan->getId()->toRfc4122().'/relations/reverse/count',
        )->toArray();
        self::assertSame($orphan->getId()->toRfc4122(), $body['targetObjectId']);
        self::assertSame(0, $body['count']);
        self::assertFalse($body['hasReverse']);
    }

    #[Test]
    public function reverseCountReflectsIncomingLinks(): void
    {
        $client = $this->authenticatedClient();
        $target = $this->makeProduct('REV-COUNT-TGT');
        $sourceA = $this->makeProduct('REV-COUNT-A');
        $sourceB = $this->makeProduct('REV-COUNT-B');

        $client->request('PUT', '/api/objects/'.$sourceA->getId()->toRfc4122().'/relations/cross_sell', [
            'json' => ['targets' => [['id' => $target->getId()->toRfc4122()]]],
        ]);
        $client->request('PUT', '/api/objects/'.$sourceB->getId()->toRfc4122().'/relations/up_sell', [
            'json' => ['targets' => [['id' => $target->getId()->toRfc4122()]]],
        ]);

        $body = $client->request(
            'GET',
            '/api/objects/'.$target->getId()->toRfc4122().'/relations/reverse/count',
        )->toArray();
        self::assertSame(2, $body['count']);
        self::assertTrue($body['hasReverse']);
    }

    #[Test]
    public function reverseReturns404ForUnknownObjectId(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/objects/01234567-1234-7000-8000-000000000000/relations/reverse');
        self::assertResponseStatusCodeSame(404);
    }

    private function makeProduct(string $sku): CatalogObject
    {
        $em = $this->em();
        $tenant = $em->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);
        self::getContainer()->get(TenantContext::class)->set($tenant);

        $productType = self::getContainer()->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind(ObjectKind::Product, $tenant);
        \assert(null !== $productType);

        $object = new CatalogObject($productType, $sku);
        $em->persist($object);
        $em->flush();

        return $object;
    }
}
