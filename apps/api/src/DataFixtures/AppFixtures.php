<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Catalog\Domain\Entity\Product;
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
 * Sprint 0 demo dataset.
 *
 * Two tenants ("demo" and "acme") each with one admin user and three products.
 * The pair lets the smoke test for tenant isolation (#12 / 0.0.12) flip
 * APP_DEFAULT_TENANT_CODE between them and assert that the TenantFilter
 * actually scopes queries; once auth lands (#4 / 0.0.4) callers obtain a JWT
 * for the desired tenant's admin instead of relying on the env fallback.
 *
 * Bootstrap order matters: tenants are persisted first (without flushing the
 * unit of work), then for each tenant we set TenantContext and persist its
 * products so the assignment listener stamps tenant_id correctly. Without the
 * context flip products of the second tenant would inherit the first.
 */
class AppFixtures extends Fixture
{
    private const string DEFAULT_ADMIN_PASSWORD = 'changeme';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly RbacSeeder $rbacSeeder,
        private readonly RoleRepository $roleRepository,
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

            foreach ($catalog[$tenant->getCode()] as [$sku, $name, $brand]) {
                $product = new Product($sku, $name);
                $product->setBrand($brand);
                $product->setDescription(\sprintf('Seeded demo product for tenant %s.', $tenant->getCode()));
                $manager->persist($product);
            }

            $manager->flush();
        }

        $this->tenantContext->clear();
    }
}
