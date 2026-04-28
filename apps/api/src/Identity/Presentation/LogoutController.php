<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * MVP logout endpoint — placeholder.
 *
 * JWT is stateless and there is no native invalidation path; without a
 * persisted refresh-token table we cannot revoke a still-valid access token.
 * #28 lands the refresh-token rotation, persistence, and cookie clearing —
 * this controller becomes the entry point for the full logout flow then.
 *
 * In the meantime we accept POST /api/auth/logout and return 204 so the
 * Refine SPA can wire its logout button against a real endpoint and unit
 * tests can assert the contract is reachable. Clients are expected to drop
 * the access token client-side until #28+#29 add server-side invalidation.
 */
final class LogoutController
{
    #[Route(path: '/api/auth/logout', methods: ['POST'], name: 'api_auth_logout')]
    public function __invoke(): Response
    {
        return new Response(null, Response::HTTP_NO_CONTENT);
    }
}
