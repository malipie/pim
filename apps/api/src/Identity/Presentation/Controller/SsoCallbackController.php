<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\Sso\GoogleAuthProvider;
use App\Identity\Application\Sso\MicrosoftAuthProvider;
use App\Identity\Application\Sso\SamlAuthProvider;
use App\Identity\Application\Sso\SsoUserResolver;
use App\Identity\Domain\Attribute\NoPermissionRequired;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RBAC-P2 SSO callback endpoints — Google Workspace today (#661);
 * Microsoft 365 (#662) + SAML (#663) add their own callback paths.
 *
 * Flow per provider:
 *   GET /api/auth/sso/{tenant}/{provider}/login
 *     → 302 redirect do provider's authorization URL z state CSRF token
 *   GET /api/auth/sso/{tenant}/{provider}/callback?code=...&state=...
 *     → verify state (cookie-based), exchange code for token, fetch
 *       email, resolve via SsoUserResolver, issue PIM JWT, redirect
 *       do admin SPA z JWT in URL fragment.
 *
 * State CSRF: stored w short-lived signed cookie (10 min TTL). Same
 * pattern as Lexik's refresh-token cookie — httpOnly + secure +
 * SameSite=Lax (Lax bo provider redirects across origins).
 */
final class SsoCallbackController extends AbstractController
{
    private const string STATE_COOKIE = 'pim_sso_state';
    private const int STATE_TTL_SECONDS = 600;

    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly GoogleAuthProvider $google,
        private readonly MicrosoftAuthProvider $microsoft,
        private readonly SamlAuthProvider $saml,
        private readonly SsoUserResolver $userResolver,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly string $appBaseUrl = 'https://pim.localhost',
    ) {
    }

    #[Route(
        path: '/api/auth/sso/{tenantCode}/google/login',
        methods: ['GET'],
        name: 'api_auth_sso_google_login',
    )]
    #[NoPermissionRequired(reason: 'SSO login is the entry point — no session yet.')]
    public function googleLogin(string $tenantCode): RedirectResponse
    {
        $tenant = $this->tenants->findByCode($tenantCode);
        if (null === $tenant) {
            throw new NotFoundHttpException(\sprintf('Tenant "%s" not found.', $tenantCode));
        }

        $redirectUri = \sprintf('%s/api/auth/sso/%s/google/callback', $this->appBaseUrl, $tenantCode);

        try {
            $auth = $this->google->authorizationUrl($tenant, $redirectUri);
        } catch (RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $response = new RedirectResponse($auth['url']);
        $response->headers->setCookie(\Symfony\Component\HttpFoundation\Cookie::create(
            self::STATE_COOKIE,
            $auth['state'],
            time() + self::STATE_TTL_SECONDS,
            '/api/auth/sso',
            null,
            true,    // secure
            true,    // httpOnly
            false,
            'lax',   // sameSite=Lax — Google redirect needs cross-origin cookie
        ));

        return $response;
    }

    #[Route(
        path: '/api/auth/sso/{tenantCode}/google/callback',
        methods: ['GET'],
        name: 'api_auth_sso_google_callback',
    )]
    #[NoPermissionRequired(reason: 'SSO callback verifies state + provider claim; that is the auth factor.')]
    public function googleCallback(string $tenantCode, Request $request): Response
    {
        $tenant = $this->tenants->findByCode($tenantCode);
        if (null === $tenant) {
            throw new NotFoundHttpException(\sprintf('Tenant "%s" not found.', $tenantCode));
        }

        $code = $request->query->get('code', '');
        $state = $request->query->get('state', '');
        $cookieState = $request->cookies->get(self::STATE_COOKIE, '');

        if ('' === $code || '' === $state || '' === $cookieState || !hash_equals($cookieState, $state)) {
            throw new BadRequestHttpException('Invalid OAuth callback (state mismatch or missing code).');
        }

        $redirectUri = \sprintf('%s/api/auth/sso/%s/google/callback', $this->appBaseUrl, $tenantCode);

        try {
            $email = $this->google->fetchUserEmail($tenant, $code, $redirectUri);
        } catch (RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $user = $this->userResolver->resolveOrProvision($tenant, $email);

        $jwt = $this->jwtManager->create($user);

        // Return JSON (the admin SPA fetches this URL via JS, not via
        // <a> click — easier to consume than URL-fragment redirect).
        // Future Phase 4 #678 (session bootstrap) wires the SPA-side
        // initiator that triggers the popup → reads JSON → stores JWT.
        $response = new JsonResponse([
            'token' => $jwt,
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
            ],
            'tenant' => [
                'id' => $tenant->getId()->toRfc4122(),
                'code' => $tenant->getCode(),
            ],
        ]);

        // Clear state cookie after successful auth.
        $response->headers->clearCookie(self::STATE_COOKIE, '/api/auth/sso');

        return $response;
    }

    #[Route(
        path: '/api/auth/sso/{tenantCode}/microsoft/login',
        methods: ['GET'],
        name: 'api_auth_sso_microsoft_login',
    )]
    #[NoPermissionRequired(reason: 'SSO login entry point.')]
    public function microsoftLogin(string $tenantCode): RedirectResponse
    {
        $tenant = $this->tenants->findByCode($tenantCode);
        if (null === $tenant) {
            throw new NotFoundHttpException(\sprintf('Tenant "%s" not found.', $tenantCode));
        }

        $redirectUri = \sprintf('%s/api/auth/sso/%s/microsoft/callback', $this->appBaseUrl, $tenantCode);

        try {
            $auth = $this->microsoft->authorizationUrl($tenant, $redirectUri);
        } catch (RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $response = new RedirectResponse($auth['url']);
        $response->headers->setCookie(\Symfony\Component\HttpFoundation\Cookie::create(
            self::STATE_COOKIE,
            $auth['state'],
            time() + self::STATE_TTL_SECONDS,
            '/api/auth/sso',
            null,
            true,
            true,
            false,
            'lax',
        ));

        return $response;
    }

    #[Route(
        path: '/api/auth/sso/{tenantCode}/microsoft/callback',
        methods: ['GET'],
        name: 'api_auth_sso_microsoft_callback',
    )]
    #[NoPermissionRequired(reason: 'SSO callback verifies state + provider claim.')]
    public function microsoftCallback(string $tenantCode, Request $request): Response
    {
        $tenant = $this->tenants->findByCode($tenantCode);
        if (null === $tenant) {
            throw new NotFoundHttpException(\sprintf('Tenant "%s" not found.', $tenantCode));
        }

        $code = $request->query->get('code', '');
        $state = $request->query->get('state', '');
        $cookieState = $request->cookies->get(self::STATE_COOKIE, '');

        if ('' === $code || '' === $state || '' === $cookieState || !hash_equals($cookieState, $state)) {
            throw new BadRequestHttpException('Invalid OAuth callback (state mismatch or missing code).');
        }

        $redirectUri = \sprintf('%s/api/auth/sso/%s/microsoft/callback', $this->appBaseUrl, $tenantCode);

        try {
            $email = $this->microsoft->fetchUserEmail($tenant, $code, $redirectUri);
        } catch (RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $user = $this->userResolver->resolveOrProvision($tenant, $email);
        $jwt = $this->jwtManager->create($user);

        $response = new JsonResponse([
            'token' => $jwt,
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
            ],
            'tenant' => [
                'id' => $tenant->getId()->toRfc4122(),
                'code' => $tenant->getCode(),
            ],
        ]);
        $response->headers->clearCookie(self::STATE_COOKIE, '/api/auth/sso');

        return $response;
    }

    #[Route(
        path: '/api/auth/sso/{tenantCode}/saml/login',
        methods: ['GET'],
        name: 'api_auth_sso_saml_login',
    )]
    #[NoPermissionRequired(reason: 'SAML SSO login entry point.')]
    public function samlLogin(string $tenantCode): RedirectResponse
    {
        $tenant = $this->tenants->findByCode($tenantCode);
        if (null === $tenant) {
            throw new NotFoundHttpException(\sprintf('Tenant "%s" not found.', $tenantCode));
        }

        try {
            $url = $this->saml->loginUrl($tenant);
        } catch (RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        return new RedirectResponse($url);
    }

    #[Route(
        path: '/api/auth/sso/{tenantCode}/saml/acs',
        methods: ['POST'],
        name: 'api_auth_sso_saml_acs',
    )]
    #[NoPermissionRequired(reason: 'SAML ACS verifies signed assertion from IdP; that is the auth factor.')]
    public function samlAcs(string $tenantCode): Response
    {
        $tenant = $this->tenants->findByCode($tenantCode);
        if (null === $tenant) {
            throw new NotFoundHttpException(\sprintf('Tenant "%s" not found.', $tenantCode));
        }

        try {
            $email = $this->saml->processCallback($tenant);
        } catch (RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        $user = $this->userResolver->resolveOrProvision($tenant, $email);
        $jwt = $this->jwtManager->create($user);

        return new JsonResponse([
            'token' => $jwt,
            'user' => [
                'id' => $user->getId()->toRfc4122(),
                'email' => $user->getEmail(),
            ],
            'tenant' => [
                'id' => $tenant->getId()->toRfc4122(),
                'code' => $tenant->getCode(),
            ],
        ]);
    }
}
