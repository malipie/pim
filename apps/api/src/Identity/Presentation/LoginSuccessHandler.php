<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Application\MfaLoginChallengeStore;
use App\Identity\Application\RefreshTokenService;
use App\Identity\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Wraps Lexik's authentication success handler with three concerns:
 *
 *  1. Stamp `User::recordLogin()` so the dashboard can show "last seen".
 *  2. Issue a refresh token and attach it as the httpOnly cookie.
 *  3. Return Lexik's response untouched (`{token: "..."}`) so existing
 *     clients keep working — the cookie is additive, not a replacement.
 *
 * We do not subclass Lexik's handler. Composition via constructor injection
 * keeps the contract narrow (`AuthenticationSuccessHandlerInterface`) and
 * sidesteps Symfony's decorator-with-arguments fragility — the firewall in
 * `security.yaml` references this class directly as `success_handler`.
 */
final readonly class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private AuthenticationSuccessHandlerInterface $inner,
        private RefreshTokenService $refreshTokens,
        private AuthCookieFactory $cookies,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        private MfaLoginChallengeStore $mfaChallenge,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();

        // Second factor required (#1141): the password step succeeded but the
        // user has active TOTP. Do NOT mint a JWT or refresh cookie yet —
        // park the identity behind a short-lived challenge the client redeems
        // at POST /api/auth/2fa/login with a TOTP / backup code. Without this
        // gate, enabling MFA had no effect on login.
        if ($user instanceof User && $user->isTotpEnabled()) {
            return new JsonResponse([
                'mfa_required' => true,
                'mfa_token' => $this->mfaChallenge->issue($user),
            ]);
        }

        $response = $this->inner->onAuthenticationSuccess($request, $token);
        if (!$response instanceof Response) {
            // Lexik never returns null in practice, but the interface allows
            // it so the firewall can fall through to the next listener. If
            // it ever did, we have nothing to attach the cookie to.
            return $response;
        }

        if (!$user instanceof User) {
            // Should never happen — the json_login firewall is wired to the
            // entity provider for App\Identity\Domain\Entity\User. If it
            // does, we bail early so the cookie is never attached to a
            // surprise principal.
            return $response;
        }

        $user->recordLogin($this->clock->now());

        $issued = $this->refreshTokens->issueForUser($user);
        $this->em->flush();

        $response->headers->setCookie($this->cookies->issue($issued['raw'], $this->clock->now()));

        return $response;
    }
}
