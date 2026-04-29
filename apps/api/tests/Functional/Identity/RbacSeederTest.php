<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Identity\Application\RbacSeeder;
use App\Identity\Domain\Entity\Permission;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Identity\Domain\Repository\RoleRepositoryInterface;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Coverage for #27 (0.2.4) — RBAC seeder + getRoles() merge.
 *
 * Hits a real Postgres so the unique constraints from #24 actively guard
 * idempotency: a buggy seeder duplicating rows would fail at flush.
 */
final class RbacSeederTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    #[Test]
    public function seedsAllFourBuiltInRolesWithMatrixPermissions(): void
    {
        $this->seeder()->seed();

        $roles = $this->roleRepository();

        $superAdmin = $roles->findGlobalByCode(RbacMatrix::ROLE_SUPER_ADMIN);
        $catalogManager = $roles->findGlobalByCode(RbacMatrix::ROLE_CATALOG_MANAGER);
        $integrationManager = $roles->findGlobalByCode(RbacMatrix::ROLE_INTEGRATION_MANAGER);
        $viewer = $roles->findGlobalByCode(RbacMatrix::ROLE_VIEWER);

        self::assertNotNull($superAdmin, 'super_admin must exist after seeding.');
        self::assertNotNull($catalogManager);
        self::assertNotNull($integrationManager);
        self::assertNotNull($viewer);

        // Super admin gets every (resource, action) — the matrix lists 13
        // resources times 4 actions = 52 permissions today.
        self::assertCount(\count(RbacMatrix::permissions()), $superAdmin->getPermissions());

        // Viewer is read-only: no write/delete/admin pairs in its set.
        foreach ($viewer->getPermissions() as $permission) {
            self::assertSame(RbacMatrix::ACTION_READ, $permission->getAction(), 'Viewer must only have read permissions.');
        }

        // Catalog manager must NOT have channel.write — that's integration territory.
        $catalogPermissions = array_map(static fn (Permission $p): string => $p->getCode(), $catalogManager->getPermissions()->toArray());
        self::assertNotContains('channel.write', $catalogPermissions);
        self::assertContains('object.write', $catalogPermissions);
        self::assertContains('attribute.write', $catalogPermissions);

        // Integration manager has read on object but not write.
        $integrationPermissions = array_map(static fn (Permission $p): string => $p->getCode(), $integrationManager->getPermissions()->toArray());
        self::assertContains('object.read', $integrationPermissions);
        self::assertNotContains('object.write', $integrationPermissions);
        self::assertContains('channel.write', $integrationPermissions);
    }

    #[Test]
    public function rerunningTheSeederIsIdempotent(): void
    {
        $first = $this->seeder()->seed();
        $second = $this->seeder()->seed();

        self::assertGreaterThan(0, $first->permissionsCreated, 'First run must create permission rows.');
        self::assertGreaterThan(0, $first->rolesCreated, 'First run must create role rows.');

        self::assertTrue($second->isNoOp(), \sprintf(
            'Second run must be a no-op: created %d/%d permissions/roles, updated %d roles.',
            $second->permissionsCreated,
            $second->rolesCreated,
            $second->rolesUpdated,
        ));
    }

    #[Test]
    public function getRolesMergesM2mGraphWithLegacyJsonColumn(): void
    {
        $this->seeder()->seed();
        $em = $this->em();

        $tenant = new Tenant('alpha', 'Alpha');
        $em->persist($tenant);
        $em->flush();

        $catalogManager = $this->roleRepository()->findGlobalByCode(RbacMatrix::ROLE_CATALOG_MANAGER);
        self::assertNotNull($catalogManager);

        $user = new User($tenant, 'kasia@alpha.test', '', ['ROLE_LEGACY']);
        $user->addRole($catalogManager);
        $em->persist($user);
        $em->flush();

        $em->clear();

        /** @var User $reloaded */
        $reloaded = $em->getRepository(User::class)->findOneBy(['email' => 'kasia@alpha.test']);
        $resolved = $reloaded->getRoles();

        // ROLE_USER is implicit, ROLE_LEGACY comes from the JSON column,
        // ROLE_CATALOG_MANAGER comes from the M2M edge.
        self::assertContains('ROLE_USER', $resolved);
        self::assertContains('ROLE_LEGACY', $resolved);
        self::assertContains('ROLE_CATALOG_MANAGER', $resolved);
        self::assertSame(\count($resolved), \count(array_unique($resolved)), 'getRoles() must return a deduplicated list.');
    }

    private function seeder(): RbacSeeder
    {
        return self::getContainer()->get(RbacSeeder::class);
    }

    private function roleRepository(): RoleRepositoryInterface
    {
        return self::getContainer()->get(RoleRepositoryInterface::class);
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
