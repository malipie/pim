<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;

use const JSON_THROW_ON_ERROR;

/**
 * UP-03 (#1019) — `/api/objects/{id}/categories` poly-kind capability-gated.
 *
 * Built-in product is seeded `isCategorizable=true`, so the new route
 * works identically to the legacy `/api/products/{id}/categories`. Custom
 * kinds without the flag get 422 — operator must flip the flag in the
 * modeling wizard.
 */
final class ObjectCategoryAssignmentApiTest extends CatalogApiTestCase
{
    #[Test]
    public function listReturnsEmptyForFreshProduct(): void
    {
        $client = $this->authenticatedClient();
        $productId = $this->createProduct($client);

        $response = $client->request('GET', '/api/objects/'.$productId.'/categories');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $response->toArray();
        self::assertSame($productId, $body['objectId']);
        self::assertSame([], $body['assignments']);
    }

    #[Test]
    public function listRejectsNonCategorizableCustomKindWith422(): void
    {
        $client = $this->authenticatedClient();
        $customOtId = $this->seedCustomObjectType('test_noncategorizable', isCategorizable: false);
        $customObjectId = $this->createCustomObject($client, $customOtId, 'NON-CAT-001');

        $client->request('GET', '/api/objects/'.$customObjectId.'/categories');
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function listWorksForCategorizableCustomKind(): void
    {
        $client = $this->authenticatedClient();
        $customOtId = $this->seedCustomObjectType('test_categorizable', isCategorizable: true);
        $customObjectId = $this->createCustomObject($client, $customOtId, 'CAT-001');

        $response = $client->request('GET', '/api/objects/'.$customObjectId.'/categories');
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $response->toArray();
        self::assertSame([], $body['assignments']);
    }

    #[Test]
    public function unknownObjectReturns404(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/objects/01234567-1234-7000-8000-000000000000/categories');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function createProduct(\ApiPlatform\Symfony\Bundle\Test\Client $client): string
    {
        $response = $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'PROD-CAT-TEST',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = $response->toArray()['id'] ?? null;
        \assert(\is_string($id));

        return $id;
    }

    private function createCustomObject(
        \ApiPlatform\Symfony\Bundle\Test\Client $client,
        string $objectTypeId,
        string $code,
    ): string {
        $response = $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => $code,
                'objectTypeId' => $objectTypeId,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = $response->toArray()['id'] ?? null;
        \assert(\is_string($id));

        return $id;
    }

    private function seedCustomObjectType(string $code, bool $isCategorizable): string
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);

        $ot = new ObjectType($code, ObjectKind::Custom, ['pl' => $code, 'en' => $code]);
        $ot->setCategorizable($isCategorizable);
        $em = $this->em();
        $em->persist($ot);
        $em->flush();

        $tenantContext->clear();

        return $ot->getId()->toRfc4122();
    }
}
