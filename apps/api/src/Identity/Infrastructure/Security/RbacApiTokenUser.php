<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Symfony Security principal representing an RBAC API token authentication.
 *
 * Distinct from {@see \App\ApiConfigurator\Infrastructure\Security\ApiKeyUser}:
 * that one represents the epic 0.10 ApiKey (integration-tier credential
 * managed in ApiConfigurator). This one represents the RBAC ApiToken
 * (P1-008 entity) — issued per-User with explicit scopes from PRD-PIM-rbac §3.4
 * and consumed by Phase 3 Voters (#664+) for permission resolution.
 *
 * Roles in the Symfony sense: every authenticated token bears `ROLE_USER`
 * plus `ROLE_API_TOKEN` so access_control rules can discriminate the
 * machine-auth path from a JWT-authenticated human session if needed.
 */
final class RbacApiTokenUser implements UserInterface
{
    /**
     * @param list<string> $scopes
     */
    public function __construct(
        private readonly Uuid $apiTokenId,
        private readonly Uuid $userId,
        private readonly Uuid $tenantId,
        private readonly string $tokenLast4,
        private readonly array $scopes,
    ) {
    }

    public function getApiTokenId(): Uuid
    {
        return $this->apiTokenId;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getTenantId(): Uuid
    {
        return $this->tenantId;
    }

    public function getTokenLast4(): string
    {
        return $this->tokenLast4;
    }

    /**
     * @return list<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    public function hasScope(string $scope): bool
    {
        return \in_array($scope, $this->scopes, true);
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return ['ROLE_USER', 'ROLE_API_TOKEN'];
    }

    public function getUserIdentifier(): string
    {
        return 'api-token:'.$this->apiTokenId->toRfc4122();
    }

    public function eraseCredentials(): void
    {
        // No transient secrets to clear — tokenHash never enters this object.
    }
}
