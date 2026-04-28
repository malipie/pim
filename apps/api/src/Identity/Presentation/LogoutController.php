<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Application\RefreshTokenService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/auth/logout — revoke the active refresh token and clear the cookie.
 *
 * Always returns 204 even if the cookie is missing or already invalid: a
 * client logging out is signalling intent, not asking for verification, and
 * a non-idempotent logout would surprise React after a full-page refresh.
 *
 * The access token (Bearer JWT) survives until its 1 h TTL expires — that's
 * a known limitation of stateless JWT and is mitigated by the short TTL plus
 * the cookie clearance which prevents silent re-issue via /api/auth/refresh.
 * A session-token blacklist is intentionally out of scope (epic 0.11).
 */
final readonly class LogoutController
{
    public function __construct(
        private RefreshTokenService $refreshTokens,
        private AuthCookieFactory $cookies,
    ) {
    }

    #[Route(path: '/api/auth/logout', methods: ['POST'], name: 'api_auth_logout')]
    public function __invoke(Request $request): Response
    {
        $cookieValue = $request->cookies->get($this->cookies->getCookieName());
        $this->refreshTokens->revoke($cookieValue);

        $response = new Response(null, Response::HTTP_NO_CONTENT);
        $response->headers->setCookie($this->cookies->clear());

        return $response;
    }
}
