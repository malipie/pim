<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Identity\Domain\Entity\Permission;
use App\Identity\Domain\Entity\Role;
use App\Identity\Domain\Entity\Tenant;
use App\Identity\Domain\Entity\User;
use App\Identity\Infrastructure\Doctrine\Repository\RoleRepository;
use App\Identity\Infrastructure\Doctrine\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Coverage for #24 (0.2.1) — RBAC schema baseline.
 *
 * Validates the new entities and junction tables hit a real Postgres
 * (per the project rule: integration tests do not mock the database) so we
 * actually exercise the migration shape, not a fictional in-memory schema.
 */
final class RbacSchemaTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    #[Test]
    public function roleRepositoryDistinguishesGlobalAndTenantScopedRoles(): void
    {
        $em = $this->em();

        $tenant = new Tenant('alpha', 'Alpha');
        $em->persist($tenant);
        $em->flush();

        $globalAdmin = new Role('super_admin', 'Super Admin');
        $tenantAdmin = new Role('super_admin', 'Alpha Super Admin', $tenant);
        $em->persist($globalAdmin);
        $em->persist($tenantAdmin);
        $em->flush();

        $repository = $this->roleRepository();

        $resolvedGlobal = $repository->findGlobalByCode('super_admin');
        self::assertNotNull($resolvedGlobal);
        self::assertTrue($resolvedGlobal->isGlobal());

        $resolvedTenant = $repository->findByCode('super_admin', $tenant);
        self::assertNotNull($resolvedTenant);
        self::assertSame($tenant, $resolvedTenant->getTenant());

        self::assertNotSame($resolvedGlobal->getId(), $resolvedTenant->getId(), 'Global and tenant role with the same code must be distinct rows.');
    }

    #[Test]
    public function permissionUniquenessIsEnforcedOnResourceActionPair(): void
    {
        $em = $this->em();

        $first = new Permission('product', 'write');
        $em->persist($first);
        $em->flush();

        $duplicate = new Permission('product', 'write', code: 'product.write.alt');
        $em->persist($duplicate);

        $this->expectException(UniqueConstraintViolationException::class);
        $em->flush();
    }

    #[Test]
    public function userRoleAssignmentIsBidirectionallyVisibleAfterReload(): void
    {
        $em = $this->em();

        $tenant = new Tenant('alpha', 'Alpha');
        $em->persist($tenant);
        $em->flush();

        $role = new Role('catalog_manager', 'Catalog Manager');
        $em->persist($role);

        $permission = new Permission('product', 'write');
        $role->grantPermission($permission);
        $em->persist($permission);

        $stub = new User($tenant, 'kasia@alpha.test', '', ['ROLE_USER']);
        $user = new User($tenant, 'kasia@alpha.test', '', ['ROLE_USER']);
        unset($stub);
        $user->addRole($role);
        $em->persist($user);
        $em->flush();

        $em->clear();

        $reloaded = $this->userRepository()->findByEmail('kasia@alpha.test');
        self::assertNotNull($reloaded);
        self::assertCount(1, $reloaded->getAssignedRoles(), 'User must keep its M2M role after a clear/reload cycle.');

        $reloadedRole = $reloaded->getAssignedRoles()->first();
        self::assertInstanceOf(Role::class, $reloadedRole);
        self::assertSame('catalog_manager', $reloadedRole->getCode());
        self::assertCount(1, $reloadedRole->getPermissions(), 'Role↔Permission junction must survive the reload too.');
    }

    #[Test]
    public function userStatusAndLastLoginRoundTripThroughTheDatabase(): void
    {
        $em = $this->em();

        $tenant = new Tenant('alpha', 'Alpha');
        $em->persist($tenant);
        $em->flush();

        $user = new User($tenant, 'tomasz@alpha.test', '', ['ROLE_USER']);
        $user->recordLogin();
        $user->disable();
        $em->persist($user);
        $em->flush();

        $em->clear();

        $reloaded = $this->userRepository()->findByEmail('tomasz@alpha.test');
        self::assertNotNull($reloaded);
        self::assertSame(User::STATUS_DISABLED, $reloaded->getStatus());
        self::assertNotNull($reloaded->getLastLoginAt());
    }

    #[Test]
    public function tenantDomainAndPlanRoundTripThroughTheDatabase(): void
    {
        $em = $this->em();

        $tenant = new Tenant(
            code: 'alpha',
            name: 'Alpha',
            domain: 'alpha.example.com',
            plan: Tenant::PLAN_PRO,
        );
        $em->persist($tenant);
        $em->flush();

        $em->clear();

        $reloaded = $em->getRepository(Tenant::class)->findOneBy(['code' => 'alpha']);
        self::assertNotNull($reloaded);
        self::assertSame('alpha.example.com', $reloaded->getDomain());
        self::assertSame(Tenant::PLAN_PRO, $reloaded->getPlan());
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    private function roleRepository(): RoleRepository
    {
        return self::getContainer()->get(RoleRepository::class);
    }

    private function userRepository(): UserRepository
    {
        return self::getContainer()->get(UserRepository::class);
    }
}
