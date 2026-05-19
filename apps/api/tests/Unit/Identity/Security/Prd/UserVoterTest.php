<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security\Prd;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\PermissionSet;
use App\Identity\Infrastructure\Security\Prd\UserVoter;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P3-005 (#668) — UserVoter unit coverage including the
 * self-modification guard against the `change_roles` action.
 */
final class UserVoterTest extends TestCase
{
    #[Test]
    public function grantsViewWhenManagePermissionPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $target = $this->user($tenant, 'target@alpha.localhost');
        $current = $this->user($tenant, 'admin@alpha.localhost');

        $voter = new UserVoter($this->resolverWith($current, ['settings.users.manage']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($current), $target, ['view']),
        );
    }

    #[Test]
    public function grantsInviteOnClassLevelSubject(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $current = $this->user($tenant, 'admin@alpha.localhost');

        $voter = new UserVoter($this->resolverWith($current, ['settings.users.manage']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($current), User::class, ['invite']),
        );
    }

    #[Test]
    public function deniesAcrossTenants(): void
    {
        $alpha = new Tenant('alpha', 'Alpha');
        $beta = new Tenant('beta', 'Beta');
        $target = $this->user($beta, 'someone@beta.localhost');
        $current = $this->user($alpha, 'admin@alpha.localhost');

        $voter = new UserVoter($this->resolverWith($current, ['settings.users.manage']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($current), $target, ['edit']),
        );
    }

    #[Test]
    public function deniesChangeRolesOnSelfEvenWithPermission(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $current = $this->user($tenant, 'admin@alpha.localhost');

        $voter = new UserVoter($this->resolverWith($current, ['settings.users.manage']));

        // Even Tenant Owner cannot grant themselves new roles in one PATCH —
        // protects against privilege escalation through single-call edit.
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($current), $current, ['change_roles']),
        );
    }

    #[Test]
    public function grantsEditSelfWithoutRoleChange(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $current = $this->user($tenant, 'admin@alpha.localhost');

        $voter = new UserVoter($this->resolverWith($current, ['settings.users.manage']));

        // Profile edit on self is fine — only the role-change action is
        // self-modification-locked.
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($current), $current, ['edit']),
        );
    }

    #[Test]
    public function grantsChangeRolesOnOtherUser(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $target = $this->user($tenant, 'colleague@alpha.localhost');
        $current = $this->user($tenant, 'admin@alpha.localhost');

        $voter = new UserVoter($this->resolverWith($current, ['settings.users.manage']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($current), $target, ['change_roles']),
        );
    }

    #[Test]
    public function deniesAllActionsWithoutPermission(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $target = $this->user($tenant, 'colleague@alpha.localhost');
        $current = $this->user($tenant, 'employee@alpha.localhost');

        $voter = new UserVoter($this->resolverWith($current, []));

        foreach (['view', 'invite', 'edit', 'deactivate', 'reactivate', 'change_roles'] as $action) {
            self::assertSame(
                VoterInterface::ACCESS_DENIED,
                $voter->vote($this->token($current), $target, [$action]),
                "Expected denial for action {$action}",
            );
        }
    }

    #[Test]
    public function abstainsOnUnknownAttribute(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $current = $this->user($tenant, 'admin@alpha.localhost');

        $voter = new UserVoter($this->resolverWith($current, ['settings.users.manage']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($current), $current, ['UNKNOWN']),
        );
    }

    private function user(Tenant $tenant, string $email): User
    {
        return new User($tenant, $email, 'placeholder', ['ROLE_USER'], Uuid::v7());
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
