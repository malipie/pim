<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security\Prd;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\Role;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\PermissionSet;
use App\Identity\Infrastructure\Security\Prd\RoleVoter;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * RBAC-P3-005 (#668) — RoleVoter unit coverage including the
 * null-tenant (global) role denial.
 */
final class RoleVoterTest extends TestCase
{
    #[Test]
    public function grantsViewWhenManagePermissionPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $role = $this->tenantRole($tenant);
        $current = $this->user($tenant);

        $voter = new RoleVoter($this->resolverWith($current, ['settings.roles.manage']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($current), $role, ['view']),
        );
    }

    #[Test]
    public function grantsAddOnClassLevelSubject(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $current = $this->user($tenant);

        $voter = new RoleVoter($this->resolverWith($current, ['settings.roles.manage']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($current), Role::class, ['add']),
        );
    }

    #[Test]
    public function deniesEditOnGlobalRoleEvenWithPermission(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $globalRole = new Role('super_admin', 'Super Admin', null);
        $current = $this->user($tenant);

        $voter = new RoleVoter($this->resolverWith($current, ['settings.roles.manage']));

        // Tenant-scoped principals cannot mutate platform-owned global roles.
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($current), $globalRole, ['edit']),
        );
    }

    #[Test]
    public function deniesAcrossTenants(): void
    {
        $alpha = new Tenant('alpha', 'Alpha');
        $beta = new Tenant('beta', 'Beta');
        $role = $this->tenantRole($beta);
        $current = $this->user($alpha);

        $voter = new RoleVoter($this->resolverWith($current, ['settings.roles.manage']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($current), $role, ['edit']),
        );
    }

    #[Test]
    public function deniesEverythingWithoutPermission(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $role = $this->tenantRole($tenant);
        $current = $this->user($tenant);

        $voter = new RoleVoter($this->resolverWith($current, []));

        foreach (['view', 'add', 'edit', 'delete'] as $action) {
            self::assertSame(
                VoterInterface::ACCESS_DENIED,
                $voter->vote($this->token($current), $role, [$action]),
                "Expected denial for action {$action}",
            );
        }
    }

    #[Test]
    public function abstainsOnUnknownAttribute(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $role = $this->tenantRole($tenant);
        $current = $this->user($tenant);

        $voter = new RoleVoter($this->resolverWith($current, ['settings.roles.manage']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($current), $role, ['UNKNOWN']),
        );
    }

    private function tenantRole(Tenant $tenant): Role
    {
        return new Role('catalog_manager', 'Catalog Manager', $tenant);
    }

    private function user(Tenant $tenant): User
    {
        return new User($tenant, 'tester@'.$tenant->getCode().'.localhost', 'placeholder');
    }

    /**
     * @param list<string> $codes
     */
    private function resolverWith(User $user, array $codes): PermissionResolverInterface
    {
        $resolver = $this->createMock(PermissionResolverInterface::class);
        $resolver->method('resolve')->with($user)->willReturn(new PermissionSet($codes));

        return $resolver;
    }

    private function token(User $user): UsernamePasswordToken
    {
        return new UsernamePasswordToken($user, 'main');
    }
}
