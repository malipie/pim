<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security\Prd;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\PermissionSet;
use App\Identity\Infrastructure\Security\Prd\IntegrationVoter;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * RBAC-P3-006 (#669) — IntegrationVoter unit coverage of the
 * manage vs secrets split.
 */
final class IntegrationVoterTest extends TestCase
{
    #[Test]
    public function grantsViewWithManagePermission(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new IntegrationVoter($this->resolverWith($user, ['settings.integrations.manage']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), IntegrationVoter::SUBJECT_PLACEHOLDER, ['view']),
        );
    }

    #[Test]
    public function grantsManageConfigWithManagePermission(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new IntegrationVoter($this->resolverWith($user, ['settings.integrations.manage']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), IntegrationVoter::SUBJECT_PLACEHOLDER, ['manage_config']),
        );
    }

    #[Test]
    public function deniesReadSecretsWithoutSecretsPermission(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        // Even with the broad manage gate, the secrets layer requires the
        // dedicated code — Phase 6 ensures no surface bypass.
        $voter = new IntegrationVoter($this->resolverWith($user, ['settings.integrations.manage']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($user), IntegrationVoter::SUBJECT_PLACEHOLDER, ['read_secrets']),
        );
    }

    #[Test]
    public function grantsReadSecretsWhenBothPresent(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new IntegrationVoter($this->resolverWith($user, [
            'settings.integrations.manage',
            'settings.integration_secrets.read',
        ]));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($user), IntegrationVoter::SUBJECT_PLACEHOLDER, ['read_secrets']),
        );
    }

    #[Test]
    public function deniesEverythingWithoutPermission(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new IntegrationVoter($this->resolverWith($user, []));

        foreach (['view', 'manage_config', 'manage_webhooks', 'read_secrets'] as $action) {
            self::assertSame(
                VoterInterface::ACCESS_DENIED,
                $voter->vote($this->token($user), IntegrationVoter::SUBJECT_PLACEHOLDER, [$action]),
                "Expected denial for action {$action}",
            );
        }
    }

    #[Test]
    public function abstainsOnUnknownAttribute(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new IntegrationVoter($this->resolverWith($user, ['settings.integrations.manage']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), IntegrationVoter::SUBJECT_PLACEHOLDER, ['UNKNOWN']),
        );
    }

    #[Test]
    public function abstainsOnUnsupportedSubject(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $user = $this->user($tenant);

        $voter = new IntegrationVoter($this->resolverWith($user, ['settings.integrations.manage']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($user), 'something_else', ['view']),
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
