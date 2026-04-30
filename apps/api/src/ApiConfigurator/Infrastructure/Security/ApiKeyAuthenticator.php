<?php

declare(strict_types=1);

namespace App\ApiConfigurator\Infrastructure\Security;

use App\ApiConfigurator\Domain\Repository\ApiKeyRepositoryInterface;
use App\ApiConfigurator\Domain\Service\ApiKeyHasherInterface;
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
 * Authenticates `X-API-Key: pim_<env>_<32 chars>` requests on the
 * `^/api` firewall. Coexists with the JWT authenticator — Symfony
 * runs both, the one whose `supports()` returns `true` takes the
 * request. Header presence is the discriminator.
 *
 * Lookup is two-step:
 *   1. Find the `ApiKey` row by the leading 12 chars (`keyPrefix`,
 *      indexed unique). Cheap — single B-tree probe.
 *   2. Argon2id `verify()` against the stored digest. Slow (~20 ms
 *      with PHP defaults) but only fires after the prefix matches —
 *      avoids the hot-path verify on every wrong header.
 *
 * Tenant context is bound here so the request lifecycle through
 * Doctrine + serializer reads the right `tenant_id` filter, mirroring
 * what `CurrentTenantProvider` does for JWT-authenticated users.
 */
final class ApiKeyAuthenticator extends AbstractAuthenticator
{
    private const string HEADER = 'X-API-Key';
    private const int PREFIX_LENGTH = 12;

    public function __construct(
        private readonly ApiKeyRepositoryInterface $apiKeys,
        private readonly ApiKeyHasherInterface $hasher,
        private readonly TenantRepositoryInterface $tenants,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->headers->has(self::HEADER);
    }

    public function authenticate(Request $request): Passport
    {
        $rawKey = (string) $request->headers->get(self::HEADER);
        if ('' === $rawKey || \strlen($rawKey) < self::PREFIX_LENGTH) {
            throw new CustomUserMessageAuthenticationException('API key header is empty.');
        }

        $prefix = substr($rawKey, 0, self::PREFIX_LENGTH);
        $apiKey = $this->apiKeys->findByKeyPrefix($prefix);
        if (null === $apiKey) {
            throw new CustomUserMessageAuthenticationException('Unknown API key.');
        }
        if (!$apiKey->isUsable(new DateTimeImmutable())) {
            throw new CustomUserMessageAuthenticationException('API key is revoked or expired.');
        }
        if (!$this->hasher->verify($rawKey, $apiKey->getKeyHash())) {
            throw new CustomUserMessageAuthenticationException('Invalid API key.');
        }

        $tenant = $apiKey->getTenant();
        if (null === $tenant) {
            throw new CustomUserMessageAuthenticationException('API key is missing tenant context.');
        }
        // Re-fetch the tenant in the active EntityManager — `find()` on the
        // injected entity from the session ensures the TenantFilter sees a
        // managed instance, not a stale read from the security cache.
        $managedTenant = $this->tenants->findById($tenant->getId());
        if (null === $managedTenant) {
            throw new CustomUserMessageAuthenticationException('Tenant not found.');
        }
        $this->tenantContext->set($managedTenant);

        // Bump last_used_at on every successful authentication. Keeps the
        // rotation playbook honest — dead keys are easy to spot.
        $apiKey->markUsed(new DateTimeImmutable());
        $this->apiKeys->save($apiKey);

        // Re-hash on the fly if PHP defaults moved past the digest the
        // row was minted with (ADR-0016 rotation strategy).
        if ($this->hasher->needsRehash($apiKey->getKeyHash())) {
            $apiKey->rehash($this->hasher->hash($rawKey));
            $this->apiKeys->save($apiKey);
        }

        $keyPrefix = $apiKey->getKeyPrefix();
        if ('' === $keyPrefix) {
            // Defensive — the schema enforces non-empty (`Length(min: 9)`),
            // but PHPStan needs the runtime narrow.
            throw new CustomUserMessageAuthenticationException('API key has empty prefix.');
        }

        $user = new ApiKeyUser(
            apiKeyId: $apiKey->getId(),
            tenantId: $managedTenant->getId(),
            keyPrefix: $keyPrefix,
            scopes: $apiKey->getScopes(),
        );

        return new SelfValidatingPassport(new UserBadge(
            $user->getUserIdentifier(),
            static fn (): ApiKeyUser => $user,
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
}
