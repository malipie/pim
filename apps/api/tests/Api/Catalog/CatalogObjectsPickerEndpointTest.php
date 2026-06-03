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

use const JSON_THROW_ON_ERROR;

/**
 * Poly-kind GetCollection at `/api/objects` (#975).
 *
 * Confirms the relation-attribute candidate picker can fetch a unified
 * list across kinds, that `?sku=` substring filtering works, that
 * cursor pagination is honored, and that the tenant filter blocks
 * cross-tenant reads.
 */
final class CatalogObjectsPickerEndpointTest extends CatalogApiTestCase
{
    private const string TENANT_B_CODE = 'other';
    private const string ADMIN_B_EMAIL = 'admin@other.localhost';

    #[Test]
    public function unauthenticatedRequestReturns401(): void
    {
        static::createClient()->request('GET', '/api/objects');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function getCollectionReturnsUnionOfKinds(): void
    {
        $client = $this->authenticatedClient();

        $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'SKU-POLY-PRODUCT',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        $client->request('POST', '/api/categories', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'CAT-POLY',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                'categoryTargetObjectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = $client->request('GET', '/api/objects', [
            'headers' => ['accept' => 'application/ld+json'],
        ]);
        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        $members = $body['member'] ?? $body['hydra:member'] ?? [];
        \assert(\is_array($members));

        $kinds = [];
        foreach ($members as $row) {
            \assert(\is_array($row));
            $kind = $row['kind'] ?? null;
            if (\is_string($kind)) {
                $kinds[$kind] = true;
            }
        }
        self::assertArrayHasKey('product', $kinds);
        self::assertArrayHasKey('category', $kinds);
    }

    #[Test]
    public function skuQueryParamFiltersByCodeSubstring(): void
    {
        $client = $this->authenticatedClient();

        $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'SKU-NEEDLE-200',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'SKU-HAYSTACK-201',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);

        $response = $client->request('GET', '/api/objects?sku=needle', [
            'headers' => ['accept' => 'application/ld+json'],
        ]);
        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        $members = $body['member'] ?? $body['hydra:member'] ?? [];
        \assert(\is_array($members));

        $codes = $this->extractCodes($members);

        self::assertContains('SKU-NEEDLE-200', $codes);
        self::assertNotContains('SKU-HAYSTACK-201', $codes);
    }

    #[Test]
    public function itemsPerPageQueryParamCapsResults(): void
    {
        $client = $this->authenticatedClient();

        for ($i = 0; $i < 5; ++$i) {
            $client->request('POST', '/api/products', [
                'headers' => ['content-type' => 'application/ld+json'],
                'body' => json_encode([
                    'code' => 'SKU-PAGE-'.$i,
                    'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
                ], JSON_THROW_ON_ERROR),
            ]);
        }

        $response = $client->request('GET', '/api/objects?itemsPerPage=2', [
            'headers' => ['accept' => 'application/ld+json'],
        ]);
        self::assertResponseIsSuccessful();
        $body = $response->toArray();
        $members = $body['member'] ?? $body['hydra:member'] ?? [];
        \assert(\is_array($members));

        self::assertCount(2, $members);
    }

    #[Test]
    public function cursorPaginationViaIdLtAdvancesPage(): void
    {
        $client = $this->authenticatedClient();

        for ($i = 0; $i < 3; ++$i) {
            $client->request('POST', '/api/products', [
                'headers' => ['content-type' => 'application/ld+json'],
                'body' => json_encode([
                    'code' => 'SKU-CURSOR-'.$i,
                    'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
                ], JSON_THROW_ON_ERROR),
            ]);
        }

        $firstPage = $client->request('GET', '/api/objects?itemsPerPage=1', [
            'headers' => ['accept' => 'application/ld+json'],
        ])->toArray();
        $members = $firstPage['member'] ?? $firstPage['hydra:member'] ?? [];
        \assert(\is_array($members));
        self::assertCount(1, $members);
        $firstRow = $members[0];
        \assert(\is_array($firstRow));
        $firstId = $firstRow['id'] ?? null;
        \assert(\is_string($firstId));

        $secondPage = $client->request('GET', '/api/objects?itemsPerPage=1&id[lt]='.$firstId, [
            'headers' => ['accept' => 'application/ld+json'],
        ])->toArray();
        $secondMembers = $secondPage['member'] ?? $secondPage['hydra:member'] ?? [];
        \assert(\is_array($secondMembers));
        self::assertCount(1, $secondMembers);
        $secondRow = $secondMembers[0];
        \assert(\is_array($secondRow));
        $secondId = $secondRow['id'] ?? null;
        \assert(\is_string($secondId));
        self::assertNotSame($firstId, $secondId);
    }

    #[Test]
    public function tenantFilterBlocksCrossTenantReads(): void
    {
        // Seed tenant A object via the standard admin client.
        $clientA = $this->authenticatedClient();
        $clientA->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'SKU-TENANT-A',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);

        // Bootstrap tenant B + admin and seed a product visible only to B.
        $tenantB = $this->bootstrapTenantB();
        $clientB = $this->authenticatedClient(self::ADMIN_B_EMAIL);
        $clientB->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'SKU-TENANT-B',
                'objectTypeId' => $this->objectTypeIdForTenant(ObjectKind::Product, $tenantB),
            ], JSON_THROW_ON_ERROR),
        ]);

        $bodyA = $clientA->request('GET', '/api/objects?itemsPerPage=200', [
            'headers' => ['accept' => 'application/ld+json'],
        ])->toArray();
        $membersA = $bodyA['member'] ?? $bodyA['hydra:member'] ?? [];
        \assert(\is_array($membersA));
        $codesA = $this->extractCodes($membersA);

        self::assertContains('SKU-TENANT-A', $codesA);
        self::assertNotContains('SKU-TENANT-B', $codesA);

        $bodyB = $clientB->request('GET', '/api/objects?itemsPerPage=200', [
            'headers' => ['accept' => 'application/ld+json'],
        ])->toArray();
        $membersB = $bodyB['member'] ?? $bodyB['hydra:member'] ?? [];
        \assert(\is_array($membersB));
        $codesB = $this->extractCodes($membersB);

        self::assertContains('SKU-TENANT-B', $codesB);
        self::assertNotContains('SKU-TENANT-A', $codesB);
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

    /**
     * Pull the `code` field out of an AP4 collection payload while keeping
     * PHPStan max happy — array_map cannot narrow `mixed` element types so
     * we iterate manually.
     *
     * @param array<array-key, mixed> $members
     *
     * @return list<string|null>
     */
    private function extractCodes(array $members): array
    {
        $codes = [];
        foreach ($members as $row) {
            if (!\is_array($row)) {
                $codes[] = null;
                continue;
            }
            $code = $row['code'] ?? null;
            $codes[] = \is_string($code) ? $code : null;
        }

        return $codes;
    }

    private function objectTypeIdForTenant(ObjectKind $kind, Tenant $tenant): string
    {
        $type = self::getContainer()
            ->get(ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind($kind, $tenant);
        \assert(null !== $type);

        return $type->getId()->toRfc4122();
    }
}
