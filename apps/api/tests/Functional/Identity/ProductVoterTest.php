<?php

declare(strict_types=1);

namespace App\Tests\Functional\Identity;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Catalog\Domain\Entity\Product;
use App\Catalog\Infrastructure\Security\ProductVoter;
use App\Identity\Application\RbacSeeder;
use App\Identity\Domain\Entity\Tenant;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Identity\Infrastructure\Doctrine\Repository\RoleRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Matrix coverage for the Product voter (#26).
 *
 * Each role × Voter attribute pair is asserted both for own-tenant and
 * cross-tenant subjects. Hits a real Postgres so role/permission graph from
 * #27 actually drives the decision rather than a mock.
 */
final class ProductVoterTest extends ApiTestCase
{
    use Factories;
    use ResetDatabase;

    protected static ?bool $alwaysBootKernel = true;

    /**
     * @return iterable<string, array{role: string, attribute: string, ownTenant: bool, expected: bool}>
     */
    public static function decisionMatrix(): iterable
    {
        // super_admin — all-yes on own tenant, all-no on cross tenant.
        yield 'super_admin reads own' => ['role' => RbacMatrix::ROLE_SUPER_ADMIN, 'attribute' => ProductVoter::READ, 'ownTenant' => true, 'expected' => true];
        yield 'super_admin writes own' => ['role' => RbacMatrix::ROLE_SUPER_ADMIN, 'attribute' => ProductVoter::UPDATE, 'ownTenant' => true, 'expected' => true];
        yield 'super_admin deletes own' => ['role' => RbacMatrix::ROLE_SUPER_ADMIN, 'attribute' => ProductVoter::DELETE, 'ownTenant' => true, 'expected' => true];
        yield 'super_admin reads cross' => ['role' => RbacMatrix::ROLE_SUPER_ADMIN, 'attribute' => ProductVoter::READ, 'ownTenant' => false, 'expected' => false];

        // catalog_manager — full CRUD on own tenant; cross tenant denied.
        yield 'catalog_manager reads own' => ['role' => RbacMatrix::ROLE_CATALOG_MANAGER, 'attribute' => ProductVoter::READ, 'ownTenant' => true, 'expected' => true];
        yield 'catalog_manager writes own' => ['role' => RbacMatrix::ROLE_CATALOG_MANAGER, 'attribute' => ProductVoter::UPDATE, 'ownTenant' => true, 'expected' => true];
        yield 'catalog_manager deletes own' => ['role' => RbacMatrix::ROLE_CATALOG_MANAGER, 'attribute' => ProductVoter::DELETE, 'ownTenant' => true, 'expected' => true];
        yield 'catalog_manager writes cross' => ['role' => RbacMatrix::ROLE_CATALOG_MANAGER, 'attribute' => ProductVoter::UPDATE, 'ownTenant' => false, 'expected' => false];

        // integration_manager — read-only on object.
        yield 'integration_manager reads own' => ['role' => RbacMatrix::ROLE_INTEGRATION_MANAGER, 'attribute' => ProductVoter::READ, 'ownTenant' => true, 'expected' => true];
        yield 'integration_manager writes own = denied' => ['role' => RbacMatrix::ROLE_INTEGRATION_MANAGER, 'attribute' => ProductVoter::UPDATE, 'ownTenant' => true, 'expected' => false];
        yield 'integration_manager deletes own = denied' => ['role' => RbacMatrix::ROLE_INTEGRATION_MANAGER, 'attribute' => ProductVoter::DELETE, 'ownTenant' => true, 'expected' => false];

        // viewer — read-only across the board.
        yield 'viewer reads own' => ['role' => RbacMatrix::ROLE_VIEWER, 'attribute' => ProductVoter::READ, 'ownTenant' => true, 'expected' => true];
        yield 'viewer writes own = denied' => ['role' => RbacMatrix::ROLE_VIEWER, 'attribute' => ProductVoter::UPDATE, 'ownTenant' => true, 'expected' => false];
        yield 'viewer deletes own = denied' => ['role' => RbacMatrix::ROLE_VIEWER, 'attribute' => ProductVoter::DELETE, 'ownTenant' => true, 'expected' => false];
    }

    #[Test]
    #[DataProvider('decisionMatrix')]
    public function voterFollowsTheRbacMatrix(string $role, string $attribute, bool $ownTenant, bool $expected): void
    {
        $this->seeder()->seed();

        $em = $this->em();

        $userTenant = new Tenant('alpha', 'Alpha');
        $otherTenant = new Tenant('bravo', 'Bravo');
        $em->persist($userTenant);
        $em->persist($otherTenant);
        $em->flush();

        $user = new User($userTenant, \sprintf('user-%s@alpha.test', $role), '');
        $resolved = $this->roleRepository()->findGlobalByCode($role);
        self::assertNotNull($resolved, \sprintf('Role %s must be seeded.', $role));
        $user->addRole($resolved);
        $em->persist($user);

        $product = new Product(\sprintf('SKU-%s', $role), 'Test product');
        $product->assignTenant($ownTenant ? $userTenant : $otherTenant);
        $em->persist($product);
        $em->flush();

        $accessDecisionManager = self::getContainer()->get(AccessDecisionManagerInterface::class);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        $granted = $accessDecisionManager->decide($token, [$attribute], $product);

        self::assertSame($expected, $granted, \sprintf(
            'Role "%s" attribute "%s" on %s tenant must vote %s.',
            $role,
            $attribute,
            $ownTenant ? 'own' : 'cross',
            $expected ? 'GRANTED' : 'DENIED',
        ));
    }

    #[Test]
    public function classLevelCreateGrantsWithoutTenantInstance(): void
    {
        $this->seeder()->seed();

        $em = $this->em();
        $tenant = new Tenant('alpha', 'Alpha');
        $em->persist($tenant);
        $em->flush();

        $catalogManager = $this->roleRepository()->findGlobalByCode(RbacMatrix::ROLE_CATALOG_MANAGER);
        self::assertNotNull($catalogManager);
        $user = new User($tenant, 'kasia@alpha.test', '');
        $user->addRole($catalogManager);
        $em->persist($user);
        $em->flush();

        $accessDecisionManager = self::getContainer()->get(AccessDecisionManagerInterface::class);
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

        // Class-level subject (the FQCN string) — what API Platform passes
        // for Post / GetCollection because there is no instance yet.
        self::assertTrue($accessDecisionManager->decide($token, [ProductVoter::CREATE], Product::class));
    }

    #[Test]
    public function anonymousTokenIsAlwaysDenied(): void
    {
        $this->seeder()->seed();

        $em = $this->em();
        $tenant = new Tenant('alpha', 'Alpha');
        $em->persist($tenant);
        $em->flush();

        $product = new Product('SKU-anon', 'Anonymous probe');
        $product->assignTenant($tenant);
        $em->persist($product);
        $em->flush();

        $accessDecisionManager = self::getContainer()->get(AccessDecisionManagerInterface::class);
        // NullToken simulates an unauthenticated principal. The voter's
        // first guard (`$user instanceof User`) trips and returns DENY.
        self::assertFalse($accessDecisionManager->decide(new NullToken(), [ProductVoter::READ], $product));
    }

    private function seeder(): RbacSeeder
    {
        return self::getContainer()->get(RbacSeeder::class);
    }

    private function roleRepository(): RoleRepository
    {
        return self::getContainer()->get(RoleRepository::class);
    }

    private function em(): EntityManagerInterface
    {
        $em = self::getContainer()->get('doctrine')->getManager();
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }
}
