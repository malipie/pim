<?php

declare(strict_types=1);

namespace App\Tests\Api\Catalog;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Domain\AttributeType;
use App\Catalog\Domain\Entity\Attribute;
use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\AttributeGroupAttribute;
use App\Catalog\Domain\Entity\ObjectTypeAttribute;
use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\RelationCardinality;
use App\DataFixtures\Identity\PrdPermissionFixtures;
use App\Identity\Application\RbacSeeder;
use App\Identity\Application\SeedTenantPrdRolesService;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Shared scaffolding for Catalog ApiTestCase suites (#41 / 0.4.1).
 *
 * Seeds RBAC + tenant + super_admin user + built-in ObjectTypes so each
 * concrete test only needs to assert on the API surface. JWT minting via
 * `JWTTokenManagerInterface` skips the round-trip to `/api/auth/login`
 * — single kernel boot per test (lessons #0.0.4).
 */
abstract class CatalogApiTestCase extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    protected const string TENANT_CODE = 'demo';
    protected const string ADMIN_EMAIL = 'admin@demo.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        $em = $this->em();
        self::getContainer()->get(RbacSeeder::class)->seed();
        // RBAC-P6-005/006/007 retrofit — Phase 6 retrofit gates every
        // controller method with PRD §3.2 granular codes (products.add,
        // modeling.attribute_groups.add_edit, etc.). RbacSeeder only
        // emits the legacy RbacMatrix 4-action × 19-resource set, so
        // these tests need the PRD permission catalogue + tenant_owner
        // role assigned to the test admin to clear EndpointGuardListener.
        $prdPermissions = new PrdPermissionFixtures();
        $prdPermissions->load($em);
        $em->flush();

        $superAdmin = self::getContainer()->get(RoleRepositoryInterface::class)
            ->findGlobalByCode(RbacMatrix::ROLE_SUPER_ADMIN);
        \assert(null !== $superAdmin);

        $tenant = new Tenant(self::TENANT_CODE, 'Demo Tenant');
        $em->persist($tenant);
        $em->flush();

        self::getContainer()->get(SeedTenantPrdRolesService::class)->seed($tenant);
        $tenantOwner = self::getContainer()->get(RoleRepositoryInterface::class)
            ->findByCode('tenant_owner', $tenant);
        \assert(null !== $tenantOwner);

        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $stub = new User($tenant, self::ADMIN_EMAIL, '', ['ROLE_USER']);
        $admin = new User($tenant, self::ADMIN_EMAIL, $hasher->hashPassword($stub, 'changeme'), ['ROLE_USER']);
        $admin->addRole($superAdmin);
        $admin->addRole($tenantOwner);
        $em->persist($admin);
        $em->flush();

        // Built-in ObjectTypes (`product`, `category`, `asset`) for the tenant.
        self::getContainer()->get(BuiltInObjectTypeSeeder::class)->seed($tenant);
    }

    protected function authenticatedClient(string $email = self::ADMIN_EMAIL): \ApiPlatform\Symfony\Bundle\Test\Client
    {
        $user = self::getContainer()->get(UserRepositoryInterface::class)->findByEmail($email);
        \assert(null !== $user);

        $jwt = self::getContainer()->get(JWTTokenManagerInterface::class)->create($user);

        $client = static::createClient();
        $client->setDefaultOptions(['headers' => ['authorization' => 'Bearer '.$jwt]]);

        return $client;
    }

    protected function objectTypeIdFor(ObjectKind $kind): string
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $type = self::getContainer()
            ->get(\App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface::class)
            ->findBuiltInByKind($kind, $tenant);
        \assert(null !== $type);

        return $type->getId()->toRfc4122();
    }

    protected function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    /**
     * Test-scoped helper that re-creates the legacy "Powiązania" group +
     * five relation attributes (cross_sell, up_sell, related, alternative,
     * accessory) for suites that exercise the relations endpoints.
     *
     * MODRC-01 (#1067) dropped the production seeder per Option Y —
     * relation attributes are now user-created via the wizard. The Api
     * suite still wants a deterministic dataset to assert against, so the
     * fixture lives in test code instead of `apps/api/src/`.
     */
    protected function seedTestRelationAttributes(): void
    {
        $tenant = $this->em()->getRepository(Tenant::class)->findOneBy(['code' => self::TENANT_CODE]);
        \assert($tenant instanceof Tenant);

        $tenantContext = self::getContainer()->get(\App\Shared\Application\TenantContext::class);
        $previous = $tenantContext->get();
        $tenantContext->set($tenant);
        try {
            $productType = self::getContainer()
                ->get(\App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface::class)
                ->findBuiltInByKind(ObjectKind::Product, $tenant);
            \assert(null !== $productType);

            $em = $this->em();

            $group = new AttributeGroup(
                code: 'relations',
                label: ['pl' => 'Powiązania', 'en' => 'Relations'],
                position: 500,
                description: null,
                icon: 'Link2',
                color: '#0EA5E9',
                isSystemGroup: false,
                autoAttached: false,
                isRequiredSection: false,
                isShared: false,
                hasConditionalVisibility: false,
            );
            $em->persist($group);
            $em->persist(new ObjectTypeAttributeGroup($productType, $group, position: 500));
            $em->flush();

            $productTargetIds = [$productType->getId()->toRfc4122()];
            $definitions = [
                'cross_sell' => ['Sprzedaż krzyżowa', 'Cross-sell', 10],
                'up_sell' => ['Sprzedaż dodatkowa', 'Up-sell', 20],
                'related' => ['Powiązane', 'Related', 30],
                'alternative' => ['Alternatywne', 'Alternative', 40],
                'accessory' => ['Akcesoria', 'Accessory', 50],
            ];
            foreach ($definitions as $code => [$labelPl, $labelEn, $position]) {
                $attribute = new Attribute($code, ['pl' => $labelPl, 'en' => $labelEn], AttributeType::Relation);
                $attribute->reorder($position);
                $attribute->setRelationTargetObjectTypeIds($productTargetIds);
                $attribute->setRelationCardinality(RelationCardinality::Many);
                $attribute->setRelationAdvanced(false);
                $em->persist($attribute);
                $em->flush();

                $em->persist(new AttributeGroupAttribute(
                    attributeGroup: $group,
                    attribute: $attribute,
                    position: $position,
                ));
                $em->persist(new ObjectTypeAttribute(
                    objectType: $productType,
                    attribute: $attribute,
                    requiredForCompleteness: false,
                    sortOrder: 500 + $position,
                ));
                $em->flush();
            }
        } finally {
            if (null === $previous) {
                $tenantContext->clear();
            } else {
                $tenantContext->set($previous);
            }
        }
    }
}
