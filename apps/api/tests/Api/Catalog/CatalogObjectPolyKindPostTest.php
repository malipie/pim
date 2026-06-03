<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Identity\Application\SeedTenantPrdRolesService;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use const JSON_THROW_ON_ERROR;

/**
 * Poly-kind Post at `/api/objects` (#981).
 *
 * The relation picker modal's "Utwórz i podepnij" flow targets this
 * endpoint when the chosen ObjectType is a custom kind (no sugar path).
 * CatalogObjectProcessor::expectedKindFor() reads ObjectType.kind from
 * the row keyed by `objectTypeId` in the payload, then delegates to the
 * same CreateCatalogObjectHandler that the per-kind sugar paths use.
 */
final class CatalogObjectPolyKindPostTest extends CatalogApiTestCase
{
    private const string TENANT_B_CODE = 'other';
    private const string ADMIN_B_EMAIL = 'admin@other.localhost';

    #[Test]
    public function unauthenticatedRequestReturns401(): void
    {
        static::createClient()->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'POLY-POST-UNAUTH',
                'objectTypeId' => '019e5dd4-476a-7ea5-84df-94f36107f3b4',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    #[Test]
    public function postCreatesProductWhenObjectTypeIdResolvesToProductKind(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'POLY-POST-PRODUCT',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = $response->toArray();
        self::assertSame('POLY-POST-PRODUCT', $body['code'] ?? null);
        self::assertSame('product', $body['kind'] ?? null);
    }

    #[Test]
    public function postCreatesCategoryWhenObjectTypeIdResolvesToCategoryKind(): void
    {
        $client = $this->authenticatedClient();

        $response = $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'poly_post_category',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                // ADR-015 — a category must declare the categorizable
                // ObjectType tree it joins (Product is the only built-in).
                'categoryTargetObjectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = $response->toArray();
        self::assertSame('poly_post_category', $body['code'] ?? null);
        self::assertSame('category', $body['kind'] ?? null);
    }

    #[Test]
    public function postCategoryWithoutTargetObjectTypeReturns422(): void
    {
        // ADR-015 — categoryTargetObjectTypeId is mandatory for categories.
        $client = $this->authenticatedClient();

        $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'adr015_noscope',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function postCategoryWithNonCategorizableTargetReturns422(): void
    {
        // ADR-015 — the target ObjectType must be is_categorizable. Asset is not.
        $client = $this->authenticatedClient();

        $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'adr015_asset_scope',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                'categoryTargetObjectTypeId' => $this->objectTypeIdFor(ObjectKind::Asset),
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Test]
    public function sameCategoryCodeAllowedInDifferentTrees(): void
    {
        // ADR-015 — per-tree code uniqueness. Make a second categorizable OT,
        // then create the same code in both Product's tree and its tree.
        $client = $this->authenticatedClient();
        $secondTreeOtId = $this->seedCategorizableObjectType('cars_adr015', 'Cars ADR015');

        $first = $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'adr015_dup',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                'categoryTargetObjectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertSame(Response::HTTP_CREATED, $first->getStatusCode());

        $second = $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'adr015_dup',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                'categoryTargetObjectTypeId' => $secondTreeOtId,
            ], JSON_THROW_ON_ERROR),
        ]);
        // Same code, different tree → allowed.
        self::assertSame(Response::HTTP_CREATED, $second->getStatusCode());
    }

    #[Test]
    public function postCreatesObjectForCustomKindObjectType(): void
    {
        $client = $this->authenticatedClient();
        $customOtId = $this->seedCustomObjectType('salon_test', 'Salony Testowe');

        $response = $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'SALON-001',
                'objectTypeId' => $customOtId,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_CREATED);
        $body = $response->toArray();
        self::assertSame('SALON-001', $body['code'] ?? null);
        self::assertSame('custom', $body['kind'] ?? null);
    }

    #[Test]
    public function postWithUnknownObjectTypeIdReturns404(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'POLY-POST-MISSING',
                'objectTypeId' => '01234567-1234-7000-8000-000000000000',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function postWithCrossTenantObjectTypeIdReturns404(): void
    {
        $tenantB = $this->bootstrapTenantB();
        $otherTenantOtId = $this->objectTypeIdForTenant(ObjectKind::Product, $tenantB);

        // Tenant A admin tries to POST with tenant B's ObjectType id.
        $clientA = $this->authenticatedClient();
        $clientA->request('POST', '/api/objects', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'POLY-POST-XTENANT',
                'objectTypeId' => $otherTenantOtId,
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    #[Test]
    public function sugarPathPostStillRejectsKindMismatch(): void
    {
        // Regression guard — the refactor of expectedKindFor() preserves the
        // sugar-path contract: POST /api/products with a category ObjectType
        // must 422 (handler equality guard runs because extraProperties.kind
        // is set on the operation).
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/products', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'code' => 'POLY-POST-MISMATCH',
                'objectTypeId' => $this->objectTypeIdFor(ObjectKind::Category),
                'categoryTargetObjectTypeId' => $this->objectTypeIdFor(ObjectKind::Product),
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
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

    private function seedCategorizableObjectType(string $code, string $label): string
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $tenantContext = self::getContainer()->get(TenantContext::class);
        $tenantContext->set($tenant);

        $ot = new ObjectType($code, ObjectKind::Custom, ['pl' => $label, 'en' => $label]);
        $ot->setCategorizable(true);
        $em = $this->em();
        $em->persist($ot);
        $em->flush();

        $tenantContext->clear();

        return $ot->getId()->toRfc4122();
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

        self::getContainer()->get(\App\Catalog\Application\BuiltInObjectTypeSeeder::class)->seed($tenantB);

        return $tenantB;
    }
}
