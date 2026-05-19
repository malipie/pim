<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security\Prd;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\PermissionSet;
use App\Identity\Infrastructure\Security\Prd\AuditLogVoter;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * RBAC-P3-007 (#670) — AuditLogVoter unit coverage of the
 * own / cross-user / cross-tenant three-tier split.
 */
final class AuditLogVoterTest extends TestCase
{
    #[Test]
    public function grantsViewOwnForEveryTenantUser(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new AuditLogVoter($this->resolverWith($user, ['audit.view_own']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), AuditLogVoter::SUBJECT_PLACEHOLDER, ['view_own']),
        );
    }

    #[Test]
    public function deniesCrossUserWithoutPermission(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new AuditLogVoter($this->resolverWith($user, ['audit.view_own']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), AuditLogVoter::SUBJECT_PLACEHOLDER, ['view_cross_user']),
        );
    }

    #[Test]
    public function grantsCrossUserWithPermission(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new AuditLogVoter($this->resolverWith($user, ['audit.view_own', 'audit.view_cross_user']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), AuditLogVoter::SUBJECT_PLACEHOLDER, ['view_cross_user']),
        );
    }

    #[Test]
    public function deniesPlatformCrossTenantWithoutSuperAdmin(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        // Even tenant-level cross-user permission must not grant access to
        // platform-scope audit visibility — that is Super Admin's privilege.
        $voter = new AuditLogVoter($this->resolverWith($user, ['audit.view_cross_user']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), AuditLogVoter::SUBJECT_PLACEHOLDER, ['view_platform_cross_tenant']),
        );
    }

    #[Test]
    public function grantsPlatformCrossTenantWithSuperAdminCode(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new AuditLogVoter($this->resolverWith($user, ['platform.audit.view_all']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), AuditLogVoter::SUBJECT_PLACEHOLDER, ['view_platform_cross_tenant']),
        );
    }

    #[Test]
    public function abstainsOnUnknownAttribute(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new AuditLogVoter($this->resolverWith($user, ['audit.view_own']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), AuditLogVoter::SUBJECT_PLACEHOLDER, ['UNKNOWN']),
        );
    }

    #[Test]
    public function abstainsOnUnsupportedSubject(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new AuditLogVoter($this->resolverWith($user, ['audit.view_own']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), 'something_else', ['view_own']),
        );
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
