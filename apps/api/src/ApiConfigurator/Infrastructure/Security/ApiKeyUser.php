<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Infrastructure\Security;

use App\Shared\Application\Auth\ApiKeyPrincipal;
use Symfony\Component\Uid\Uuid;

/**
 * Synthetic principal for `X-API-Key` requests.
 *
 * The API key is not a person — it does not log in, does not own a
 * password, does not match an `Identity\User` row. This shim presents
 * the bare minimum the Symfony token storage expects (a username and
 * a `ROLE_API_KEY` role) plus the bits the rest of the stack needs:
 * the tenant the key was issued for and the list of profile codes the
 * key is allowed to scope to.
 *
 * Voters that call `is_granted('READ', $obj)` continue to work via
 * tenant comparison + `ROLE_API_KEY` plus the profile-driven grants
 * stamped on the request by {@see ApiKeyAuthenticator}.
 */
final readonly class ApiKeyUser implements ApiKeyPrincipal
{
    /**
     * @param non-empty-string $keyPrefix
     * @param list<string>     $scopes    profile codes this key can present
     */
    public function __construct(
        public Uuid $apiKeyId,
        public Uuid $tenantId,
        public string $keyPrefix,
        public array $scopes,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return ['ROLE_API_KEY'];
    }

    public function eraseCredentials(): void
    {
        // Nothing to erase — no password is held in the principal.
    }

    public function getUserIdentifier(): string
    {
        return $this->keyPrefix;
    }

    public function tenantId(): Uuid
    {
        return $this->tenantId;
    }

    /**
     * @return list<string>
     */
    public function scopes(): array
    {
        return $this->scopes;
    }
}
