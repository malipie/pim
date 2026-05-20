<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Application\Exception\RefreshTokenException;
use App\Identity\Application\RefreshTokenService;
use App\Identity\Domain\Attribute\NoPermissionRequired;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * POST /api/auth/refresh — rotate the refresh-token cookie and mint a new JWT.
 *
 * The endpoint is anonymous (see security.yaml access_control) because the
 * caller is by definition out of access tokens; authority comes from the
 * httpOnly refresh cookie alone. On any failure we return RFC 7807 to match
 * the rest of the auth surface (#25).
 */
final readonly class RefreshTokenController
{
    public function __construct(
        private RefreshTokenService $refreshTokens,
        private AuthCookieFactory $cookies,
        private JWTTokenManagerInterface $jwtManager,
        private ClockInterface $clock,
    ) {
    }

    #[Route(path: '/api/auth/refresh', methods: ['POST'], name: 'api_auth_refresh')]
    #[NoPermissionRequired(reason: 'Token refresh is by definition pre-authentication — caller has no access token yet, authority comes from the httpOnly refresh cookie alone.')]
    public function __invoke(Request $request): Response
    {
        $cookieValue = $request->cookies->get($this->cookies->getCookieName());
        if (null === $cookieValue || '' === $cookieValue) {
            return $this->problem(RefreshTokenException::missing());
        }

        try {
            $rotated = $this->refreshTokens->rotate($cookieValue);
        } catch (RefreshTokenException $exception) {
            return $this->problem($exception);
        }

        $jwt = $this->jwtManager->create($rotated['user']);

        $response = new JsonResponse(['token' => $jwt]);
        $response->headers->setCookie($this->cookies->issue($rotated['raw'], $this->clock->now()));

        return $response;
    }

    private function problem(RefreshTokenException $exception): JsonResponse
    {
        $status = Response::HTTP_UNAUTHORIZED;

        return new JsonResponse(
            [
                'type' => 'about:blank',
                'title' => Response::$statusTexts[$status],
                'status' => $status,
                'detail' => $exception->getMessage(),
                'reason' => $exception->reason,
            ],
            $status,
            ['Content-Type' => 'application/problem+json; charset=utf-8'],
        );
    }
}
