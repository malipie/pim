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
 * UP-01 (#1016) — Poly-kind PATCH + DELETE at `/api/objects/{id}`.
 *
 * The `UniversalDetailPage` (UP-07) needs a single endpoint per CatalogObject
 * regardless of kind so the React mutate path does not branch on
 * `/api/products/{id}` vs `/api/objects/{id}`. Both PATCH and DELETE
 * operations have no `extraProperties.kind` lock — `CatalogObjectProcessor`
 * derives the kind off the loaded entity and routes to the same handlers
 * the sugar paths use.
 */
final class CatalogObjectPolyKindPatchDeleteTest extends CatalogApiTestCase
{
    #[Test]
    public function patchUpdatesProductLikeSugarPath(): void
    {
        $client = $this->authenticatedClient();
        $createResponse = $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'POLY-PATCH-PROD',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = $createResponse->toArray()['id'] ?? null;
        self::assertIsString($id);

        // PATCH a generic system field (status) so the assertion does not
        // depend on the test database having a specific Attribute row seeded.
        $patchResponse = $client->request('PATCH', '/api/objects/'.$id, [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'body' => json_encode([
                'status' => 'published',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $patchResponse->toArray();
        self::assertSame('published', $body['status'] ?? null);
        self::assertSame('product', $body['kind'] ?? null);
    }

    #[Test]
    public function patchUpdatesCustomKindObject(): void
    {
        $client = $this->authenticatedClient();
        $customOtId = $this->seedCustomObjectType('test_custom_patch', 'Test Custom');

        $createResponse = $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'CUSTOM-PATCH-001',
                'objectTypeId' => $customOtId,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = $createResponse->toArray()['id'] ?? null;
        self::assertIsString($id);

        $patchResponse = $client->request('PATCH', '/api/objects/'.$id, [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'body' => json_encode([
                'status' => 'archived',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_OK);
        $body = $patchResponse->toArray();
        self::assertSame('custom', $body['kind'] ?? null);
        self::assertSame('archived', $body['status'] ?? null);
    }

    #[Test]
    public function deleteRemovesObjectLikeSugarPath(): void
    {
        $client = $this->authenticatedClient();
        $createResponse = $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'POLY-DEL-PROD',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = $createResponse->toArray()['id'] ?? null;
        self::assertIsString($id);

        $client->request('DELETE', '/api/objects/'.$id);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', '/api/objects/'.$id);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function deleteRemovesCustomKindObject(): void
    {
        $client = $this->authenticatedClient();
        $customOtId = $this->seedCustomObjectType('test_custom_delete', 'Test Custom Del');

        $createResponse = $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'CUSTOM-DEL-001',
                'objectTypeId' => $customOtId,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $id = $createResponse->toArray()['id'] ?? null;
        self::assertIsString($id);

        $client->request('DELETE', '/api/objects/'.$id);
        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $client->request('GET', '/api/objects/'.$id);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function patchUnknownIdReturns404(): void
    {
        $client = $this->authenticatedClient();
        $client->request('PATCH', '/api/objects/01234567-1234-7000-8000-000000000000', [
            'headers' => ['content-type' => 'application/merge-patch+json'],
            'body' => json_encode([
                'attributes' => ['name' => 'Anything'],
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function seedCustomObjectType(string $code, string $label): string
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);

        $ot = new ObjectType($code, ObjectKind::Custom, ['pl' => $label, 'en' => $label]);
        $em = $this->em();
        $em->persist($ot);
        $em->flush();

        $tenantContext->clear();

        return $ot->getId()->toRfc4122();
    }

    #[Test]
    public function duplicateCodeOnPolyCreateReturnsConflict(): void
    {
        $client = $this->authenticatedClient();
        $productOt = $this->objectTypeIdFor(ObjectKind::Product);

        $payload = json_encode([
            'code' => 'DUP-409',
            'objectTypeId' => $productOt,
            'attributes' => ['name' => 'First'],
        ], JSON_THROW_ON_ERROR);
        $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => $payload,
        ]);
        self::assertResponseStatusCodeSame(201);

        // #1415 — the duplicate used to bubble up as a raw 500 from the
        // (tenant, kind, code) unique index; now a clean 409 with the ID
        // in the Problem Details detail.
        $response = $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json', 'accept' => 'application/json'],
            'body' => $payload,
        ]);
        self::assertResponseStatusCodeSame(409);
        $body = $response->toArray(false);
        $detail = $body['detail'] ?? '';
        self::assertIsString($detail);
        self::assertStringContainsString('DUP-409', $detail);
    }
}
