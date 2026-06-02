<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Identity\Application\SeedTenantPrdRolesService;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * Poly-kind Get at `/api/objects/{id}` (#977).
 *
 * RelationInlineEditPanel calls this endpoint to fetch a target object's
 * detail by UUID without knowing its kind upfront — the per-kind sugar
 * paths (`/api/products/{id}` etc.) can't be used because the relation
 * attribute's `allowedObjectTypeIds` can span multiple kinds.
 *
 * The endpoint defines no `extraProperties.kind`, so KindItemExtension
 * no-ops; the read is still narrowed by the TenantFilter + `READ object`
 * voter.
 */
final class CatalogObjectPolyKindGetTest extends CatalogApiTestCase
{
    private const string TENANT_B_CODE = 'other';
    private const string ADMIN_B_EMAIL = 'admin@other.localhost';

    #[Test]
    public function unauthenticatedRequestReturns401(): void
    {
        static::createClient()->request('GET', '/api/objects/'.Uuid::v7()->toRfc4122());
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function getReturnsProductDetail(): void
    {
        $client = $this->authenticatedClient();
        $id = $this->createObject($client, '/api/products', 'SKU-POLY-GET-PRODUCT', ObjectKind::Product);

        $response = $client->request('GET', '/api/objects/'.$id, [
            'headers' => ['accept' => 'application/ld+json'],
        ]);
        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame($id, $body['id'] ?? null);
        self::assertSame('SKU-POLY-GET-PRODUCT', $body['code'] ?? null);
        self::assertSame('product', $body['kind'] ?? null);
    }

    #[Test]
    public function getReturnsCategoryDetail(): void
    {
        $client = $this->authenticatedClient();
        $id = $this->createObject($client, '/api/categories', 'CAT-POLY-GET', ObjectKind::Category);

        $response = $client->request('GET', '/api/objects/'.$id, [
            'headers' => ['accept' => 'application/ld+json'],
        ]);
        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        self::assertSame($id, $body['id'] ?? null);
        self::assertSame('CAT-POLY-GET', $body['code'] ?? null);
        self::assertSame('category', $body['kind'] ?? null);
    }

    #[Test]
    public function getInjectsSystemAttributeValues(): void
    {
        // #1207 — created_at/updated_at + created_by/updated_by are surfaced in
        // attributesIndexed at read time (they are never stored as ObjectValue
        // rows). Creating authenticated stamps the blameable actor (e-mail).
        $client = $this->authenticatedClient();
        $id = $this->createObject($client, '/api/products', 'SKU-SYSTEM-ATTRS', ObjectKind::Product);

        $response = $client->request('GET', '/api/objects/'.$id, [
            'headers' => ['accept' => 'application/ld+json'],
        ]);
        self::assertResponseIsSuccessful();
        $indexed = $response->toArray()['attributesIndexed'] ?? [];
        self::assertIsArray($indexed);

        self::assertArrayHasKey('created_at', $indexed);
        self::assertArrayHasKey('updated_at', $indexed);
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/',
            $indexed['created_at']['value'] ?? '',
        );

        self::assertArrayHasKey('created_by', $indexed);
        self::assertSame(self::ADMIN_EMAIL, $indexed['created_by']['value'] ?? null);
        self::assertSame(self::ADMIN_EMAIL, $indexed['updated_by']['value'] ?? null);
    }

    #[Test]
    public function getReturns404ForUnknownUuid(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/objects/'.Uuid::v7()->toRfc4122(), [
            'headers' => ['accept' => 'application/ld+json'],
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function tenantFilterBlocksCrossTenantRead(): void
    {
        // Seed tenant B + admin and create a product owned by tenant B.
        $tenantB = $this->bootstrapTenantB();
        $clientB = $this->authenticatedClient(self::ADMIN_B_EMAIL);
        $idB = $this->createObjectForTenant($clientB, '/api/products', 'SKU-TENANT-B-GET', ObjectKind::Product, $tenantB);

        // Tenant A admin must not see tenant B object via the poly-kind path.
        $clientA = $this->authenticatedClient();
        $clientA->request('GET', '/api/objects/'.$idB, [
            'headers' => ['accept' => 'application/ld+json'],
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    private function createObject(
        \ApiPlatform\Symfony\Bundle\Test\Client $client,
        string $sugarPath,
        string $code,
        ObjectKind $kind,
    ): string {
        $payload = [
            'code' => $code,
            'objectTypeId' => $this->objectTypeIdFor($kind),
        ];
        // ADR-015 — categories must declare the categorizable ObjectType tree.
        if (ObjectKind::Category === $kind) {
            $payload['categoryTargetObjectTypeId'] = $this->objectTypeIdFor(ObjectKind::Product);
        }
        $response = $client->request('POST', $sugarPath, [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
        $body = $response->toArray();
        $id = $body['id'] ?? null;
        \assert(\is_string($id));

        return $id;
    }

    private function createObjectForTenant(
        \ApiPlatform\Symfony\Bundle\Test\Client $client,
        string $sugarPath,
        string $code,
        ObjectKind $kind,
        Tenant $tenant,
    ): string {
        $response = $client->request('POST', $sugarPath, [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => $code,
                'objectTypeId' => $this->objectTypeIdForTenant($kind, $tenant),
            ], JSON_THROW_ON_ERROR),
        ]);
        $body = $response->toArray();
        $id = $body['id'] ?? null;
        \assert(\is_string($id));

        return $id;
    }

    private function objectTypeIdForTenant(ObjectKind $kind, Tenant $tenant): string
    {
        $type = self::getContainer()
            ->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind($kind, $tenant);
        \assert(null !== $type);

        return $type->getId()->toRfc4122();
    }

    private function bootstrapTenantB(): Tenant
    {
        $em = $this->em();

        $tenantB = new Tenant(self::TENANT_B_CODE, 'Other Tenant');
        $em->persist($tenantB);
        $em->flush();

        self::getContainer()->get(SeedTenantPrdRolesService::class)->seed($tenantB);
        $tenantOwnerB = self::getContainer()->get(RoleRepositoryInterface::class)
            ->findByCode('tenant_owner', $tenantB);
        \assert(null !== $tenantOwnerB);

        $superAdmin = self::getContainer()->get(RoleRepositoryInterface::class)
            ->findGlobalByCode(RbacMatrix::ROLE_SUPER_ADMIN);
        \assert(null !== $superAdmin);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $stub = new User($tenantB, self::ADMIN_B_EMAIL, '', ['ROLE_USER']);
        $adminB = new User($tenantB, self::ADMIN_B_EMAIL, $hasher->hashPassword($stub, 'changeme'), ['ROLE_USER']);
        $adminB->addRole($superAdmin);
        $adminB->addRole($tenantOwnerB);
        $em->persist($adminB);
        $em->flush();

        self::getContainer()->get(BuiltInObjectTypeSeeder::class)->seed($tenantB);

        return $tenantB;
    }
}
