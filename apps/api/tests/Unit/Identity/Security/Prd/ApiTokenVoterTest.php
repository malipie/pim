<?php

declare(strict_types=1);

namespace App\Tests\Unit\Identity\Security\Prd;

use App\Identity\Application\PermissionResolverInterface;
use App\Identity\Domain\Entity\ApiToken;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\PermissionSet;
use App\Identity\Infrastructure\Security\Prd\ApiTokenVoter;
use App\Shared\Domain\Tenant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P3-006 (#669) — ApiTokenVoter unit coverage of the
 * own / cross-user split (`api_tokens.own.crud` vs
 * `api_tokens.all.view_revoke`).
 */
final class ApiTokenVoterTest extends TestCase
{
    #[Test]
    public function grantsCreateWithOwnCrud(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $current = $this->user($tenant);

        $voter = new ApiTokenVoter($this->resolverWith($current, ['api_tokens.own.crud']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($current), ApiToken::class, ['create']),
        );
    }

    #[Test]
    public function grantsViewOwnToken(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $current = $this->user($tenant);
        $ownToken = $this->apiToken($tenant->getId(), $current->getId());

        $voter = new ApiTokenVoter($this->resolverWith($current, ['api_tokens.own.crud']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($current), $ownToken, ['view']),
        );
    }

    #[Test]
    public function deniesViewOtherUserTokenWithOnlyOwnCrud(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $current = $this->user($tenant);
        $someoneElse = Uuid::v7();
        $otherToken = $this->apiToken($tenant->getId(), $someoneElse);

        $voter = new ApiTokenVoter($this->resolverWith($current, ['api_tokens.own.crud']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($current), $otherToken, ['view']),
        );
    }

    #[Test]
    public function grantsViewOtherUserTokenWithAllViewRevoke(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $current = $this->user($tenant);
        $someoneElse = Uuid::v7();
        $otherToken = $this->apiToken($tenant->getId(), $someoneElse);

        $voter = new ApiTokenVoter($this->resolverWith($current, ['api_tokens.all.view_revoke']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($current), $otherToken, ['view']),
        );
    }

    #[Test]
    public function deniesAcrossTenantsEvenWithAllViewRevoke(): void
    {
        $alpha = new Tenant('alpha', 'Alpha');
        $beta = new Tenant('beta', 'Beta');
        $current = $this->user($alpha);
        $crossTenantToken = $this->apiToken($beta->getId(), Uuid::v7());

        $voter = new ApiTokenVoter($this->resolverWith($current, ['api_tokens.all.view_revoke']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($current), $crossTenantToken, ['view']),
        );
    }

    #[Test]
    public function grantsRevokeOwnTokenWithOwnCrud(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $current = $this->user($tenant);
        $ownToken = $this->apiToken($tenant->getId(), $current->getId());

        $voter = new ApiTokenVoter($this->resolverWith($current, ['api_tokens.own.crud']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($current), $ownToken, ['revoke']),
        );
    }

    #[Test]
    public function grantsViewAllWithAllViewRevoke(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $current = $this->user($tenant);

        $voter = new ApiTokenVoter($this->resolverWith($current, ['api_tokens.all.view_revoke']));

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($current), ApiToken::class, ['view_all']),
        );
    }

    #[Test]
    public function deniesViewAllWithOnlyOwnCrud(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $current = $this->user($tenant);

        $voter = new ApiTokenVoter($this->resolverWith($current, ['api_tokens.own.crud']));

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->token($current), ApiToken::class, ['view_all']),
        );
    }

    #[Test]
    public function abstainsOnUnknownAttribute(): void
    {
        $tenant = new Tenant('alpha', 'Alpha');
        $current = $this->user($tenant);

        $voter = new ApiTokenVoter($this->resolverWith($current, ['api_tokens.own.crud']));

        self::assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            $voter->vote($this->token($current), ApiToken::class, ['UNKNOWN']),
        );
    }

    private function apiToken(Uuid $tenantId, Uuid $userId): ApiToken
    {
        return new ApiToken(
            tenantId: $tenantId,
            userId: $userId,
            name: 'integration-test',
            tokenHash: 'hash',
            tokenLast4: '1234',
            scopes: ['read-only'],
        );
    }

    private function user(Tenant $tenant): User
    {
        return new User($tenant, 'tester@'.$tenant->getCode().'.localhost', 'placeholder', ['ROLE_USER'], Uuid::v7());
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
