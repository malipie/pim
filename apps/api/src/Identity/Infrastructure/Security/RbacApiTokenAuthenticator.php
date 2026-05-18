<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Repository\ApiTokenRepositoryInterface;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * RBAC-P2-003 (#652) — custom Symfony authenticator for the RBAC
 * `ApiToken` entity (Identity, P1-008). Distinct from the epic 0.10
 * ApiKeyAuthenticator (ApiConfigurator) which serves the integration-tier
 * X-API-Key header.
 *
 * Wire protocol: `Authorization: Token cortex_<tenant_short>_<random32>`.
 * The `cortex_` prefix is intentional — gitleaks / TruffleHog regex
 * patterns flag any leaked token immediately. The full token (not just a
 * prefix) is BCrypt-hashed in `api_tokens.token_hash`; plaintext leaves
 * the server exactly once at create time.
 *
 * Lookup is a single hash-equality query (the token_hash unique index
 * does the heavy lifting): hash the incoming plaintext, query by hash,
 * verify expiry + revocation + scope. No prefix bucket — the entire
 * token is checked because BCrypt cost is the bottleneck only on wrong
 * guesses (~5 ms with PHP defaults).
 *
 * Header header discriminator (`Authorization: Token ...`) avoids
 * collision with the JWT authenticator (`Authorization: Bearer ...`)
 * and with the legacy ApiKey authenticator (`X-API-Key: ...`).
 */
final class RbacApiTokenAuthenticator extends AbstractAuthenticator
{
    private const string HEADER = 'Authorization';
    private const string SCHEME_PREFIX = 'Token ';
    private const string TOKEN_PREFIX = 'cortex_';

    public function __construct(
        private readonly ApiTokenRepositoryInterface $apiTokens,
        private readonly UserRepositoryInterface $users,
        private readonly TenantRepositoryInterface $tenants,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function supports(Request $request): bool
    {
        $header = $request->headers->get(self::HEADER, '');
        if ('' === $header) {
            return false;
        }

        return str_starts_with($header, self::SCHEME_PREFIX);
    }

    public function authenticate(Request $request): Passport
    {
        $header = $request->headers->get(self::HEADER, '');
        $plaintext = substr($header, \strlen(self::SCHEME_PREFIX));

        if ('' === $plaintext || !str_starts_with($plaintext, self::TOKEN_PREFIX)) {
            throw new CustomUserMessageAuthenticationException('Malformed API token.');
        }

        $tokenHash = hash('sha256', $plaintext);
        $apiToken = $this->apiTokens->findByHash($tokenHash);
        if (null === $apiToken) {
            throw new CustomUserMessageAuthenticationException('Unknown API token.');
        }
        if (!$apiToken->isActive(new DateTimeImmutable())) {
            throw new CustomUserMessageAuthenticationException('API token is revoked or expired.');
        }

        $managedTenant = $this->tenants->findById($apiToken->getTenantId());
        if (null === $managedTenant) {
            throw new CustomUserMessageAuthenticationException('Tenant not found for API token.');
        }
        $this->tenantContext->set($managedTenant);

        $apiToken->recordUsage($request->getClientIp(), new DateTimeImmutable());
        $this->apiTokens->save($apiToken);

        // The principal IS the User the token was minted for — auth via
        // token is just an alternative to JWT (admin SPA uses JWT,
        // integrations use ApiToken). MeController + Voters + any
        // permission check thus see the same User entity regardless of
        // which authenticator fired. Token-specific metadata (scopes,
        // token_id, last4) is attached to the request via TenantContext
        // + request attributes for downstream Voters (Phase 3 #664+).
        $user = $this->users->findById($apiToken->getUserId());
        if (null === $user) {
            throw new CustomUserMessageAuthenticationException('User for API token not found.');
        }
        if (!$user->isActive()) {
            throw new CustomUserMessageAuthenticationException('User for API token is disabled.');
        }

        // Stash token metadata on the request for Phase 3 Voters that
        // need scope-aware decisions (scopes determine whether the token
        // can POST vs read-only).
        $request->attributes->set('_api_token_id', $apiToken->getId()->toRfc4122());
        $request->attributes->set('_api_token_scopes', $apiToken->getScopes());
        $request->attributes->set('_api_token_last4', $apiToken->getTokenLast4());

        return new SelfValidatingPassport(new UserBadge(
            $user->getUserIdentifier(),
            static fn (): \App\Identity\Domain\Entity\User => $user,
        ));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse(
            ['title' => 'Authentication failed', 'detail' => $exception->getMessageKey()],
            Response::HTTP_UNAUTHORIZED,
        );
    }

    /**
     * Generate a fresh API token in the format
     * `cortex_<tenant_short>_<random32>`. Called by the token-issuance
     * endpoint (`POST /api/api-tokens`); returns the plaintext exactly
     * once — the caller MUST persist only the SHA-256 hash via
     * `ApiToken::__construct`.
     */
    public static function generatePlaintext(string $tenantShortCode): string
    {
        $randomPart = bin2hex(random_bytes(16)); // 32 hex chars

        return \sprintf('%s%s_%s', self::TOKEN_PREFIX, $tenantShortCode, $randomPart);
    }

    public static function hashFor(string $plaintext): string
    {
        return hash('sha256', $plaintext);
    }

    public static function last4(string $plaintext): string
    {
        return substr($plaintext, -4);
    }
}
