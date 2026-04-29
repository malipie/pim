<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Application\BuiltInAssociationTypeSeeder;
use App\Catalog\Application\BuiltInObjectTypeSeeder;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Infrastructure\Doctrine\Repository\ObjectTypeRepository;
use App\Identity\Application\RbacSeeder;
use App\Identity\Application\TenantContext;
use App\Identity\Domain\Entity\Tenant;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Identity\Infrastructure\Doctrine\Repository\RoleRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Sprint 0 demo dataset, post-ADR-009 + post-#33.
 *
 * Two tenants ("demo" and "acme") each with one admin user, the three
 * built-in ObjectTypes (product / category / asset) seeded via
 * BuiltInObjectTypeSeeder, and three demo product objects (`CatalogObject`
 * with `kind='product'`). The legacy `Product` entity was dropped
 * alongside #33's data migration; demo products now live in `objects`
 * with their data folded into `attributes_indexed` per ADR-006 hybrid.
 */
class AppFixtures extends Fixture
{
    private const string DEFAULT_ADMIN_PASSWORD = 'changeme';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RbacSeeder $rbacSeeder,
        private readonly RoleRepository $roleRepository,
        private readonly BuiltInObjectTypeSeeder $builtInSeeder,
        private readonly BuiltInAssociationTypeSeeder $associationTypeSeeder,
        private readonly ObjectTypeRepository $objectTypeRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $tenants = [
            new Tenant('demo', 'Demo Tenant'),
            new Tenant('acme', 'Acme Industries'),
        ];

        foreach ($tenants as $tenant) {
            $manager->persist($tenant);
        }

        $manager->flush();

        // Seed the four built-in global roles before persisting users so the
        // admin fixture can attach the super_admin role through the M2M graph
        // rather than the legacy `roles JSON` column.
        $this->rbacSeeder->seed();

        $superAdmin = $this->roleRepository->findGlobalByCode(RbacMatrix::ROLE_SUPER_ADMIN);
        \assert(null !== $superAdmin, 'RbacSeeder must create the super_admin role.');

        // Per-tenant: built-in ObjectTypes (#33) → admin user → demo
        // catalog rows. The seeder is idempotent — `pim:db:reset` re-runs
        // are safe.
        foreach ($tenants as $tenant) {
            $this->builtInSeeder->seed($tenant);
            $this->associationTypeSeeder->seed($tenant);
        }

        $admins = [
            'demo' => 'admin@demo.localhost',
            'acme' => 'admin@acme.localhost',
        ];
        foreach ($tenants as $tenant) {
            $email = $admins[$tenant->getCode()];
            $stub = new User($tenant, $email, '', []);
            $admin = new User(
                $tenant,
                $email,
                $this->passwordHasher->hashPassword($stub, self::DEFAULT_ADMIN_PASSWORD),
                [],
            );
            $admin->addRole($superAdmin);
            $manager->persist($admin);
        }
        $manager->flush();

        $catalog = [
            'demo' => [
                ['DEMO-001', 'Demo Product One',   'Acme'],
                ['DEMO-002', 'Demo Product Two',   'Acme'],
                ['DEMO-003', 'Demo Product Three', 'Globex'],
            ],
            'acme' => [
                ['ACME-001', 'Acme Widget',        'Acme'],
                ['ACME-002', 'Acme Gadget',        'Acme'],
                ['ACME-003', 'Acme Sprocket',      'Acme'],
            ],
        ];

        foreach ($tenants as $tenant) {
            $this->tenantContext->set($tenant);

            $productType = $this->objectTypeRepository->findBuiltInByKind(ObjectKind::Product, $tenant);
            \assert(null !== $productType, 'BuiltInObjectTypeSeeder must seed the product ObjectType.');

            foreach ($catalog[$tenant->getCode()] as [$sku, $name, $brand]) {
                $object = new CatalogObject($productType, $sku);
                $object->setStatus(CatalogObject::STATUS_PUBLISHED);
                $object->setAttributesIndexed([
                    'sku' => $sku,
                    'name' => $name,
                    'brand' => $brand,
                    'description' => \sprintf('Seeded demo product for tenant %s.', $tenant->getCode()),
                ]);
                $manager->persist($object);
            }

            $manager->flush();
        }

        $this->tenantContext->clear();
    }
}
