<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Application\MfaLoginChallengeStore;
use App\Identity\Application\RefreshTokenService;
use App\Identity\Contracts\Attribute\NoPermissionRequired;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use const JSON_THROW_ON_ERROR;

/**
 * POST /api/auth/2fa/login — second factor of login (#1141).
 *
 * Completes a login that {@see LoginSuccessHandler} parked because the user
 * has active TOTP. Body: `{ "mfa_token": "...", "code": "123456" }` — the
 * code may be a TOTP code or a one-shot backup code. On success it mints the
 * JWT + refresh cookie exactly like the password step would have, and stamps
 * the login.
 *
 * Anonymous (PUBLIC_ACCESS in security.yaml): the caller has no access token
 * yet — authority comes from the opaque, short-lived challenge token minted
 * after the password step, redeemed once here.
 */
final readonly class MfaLoginController
{
    public function __construct(
        private MfaLoginChallengeStore $challenges,
        private RefreshTokenService $refreshTokens,
        private AuthCookieFactory $cookies,
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    #[Route(path: '/api/auth/2fa/login', methods: ['POST'], name: 'api_auth_2fa_login')]
    #[NoPermissionRequired(reason: 'Second factor of login — caller holds no JWT yet; authority is the short-lived challenge token minted after the password step.')]
    public function __invoke(Request $request): Response
    {
        $raw = $request->getContent();
        try {
            $body = json_decode('' === $raw ? '{}' : $raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->problem('Malformed JSON body.');
        }
        if (!\is_array($body)) {
            return $this->problem('Malformed JSON body.');
        }

        $tokenRaw = $body['mfa_token'] ?? null;
        $codeRaw = $body['code'] ?? null;
        $mfaToken = \is_string($tokenRaw) ? $tokenRaw : '';
        $code = match (true) {
            \is_string($codeRaw) => trim($codeRaw),
            \is_int($codeRaw) => (string) $codeRaw,
            default => '',
        };

        $user = $this->challenges->consume($mfaToken, $code);
        if (null === $user) {
            return $this->problem('Invalid or expired challenge, or wrong verification code.');
        }

        $jwt = $this->jwtManager->create($user);
        $user->recordLogin($this->clock->now());
        $issued = $this->refreshTokens->issueForUser($user);
        $this->em->flush();

        $response = new JsonResponse(['token' => $jwt]);
        $response->headers->setCookie($this->cookies->issue($issued['raw'], $this->clock->now()));

        return $response;
    }

    private function problem(string $detail): JsonResponse
    {
        $status = Response::HTTP_UNAUTHORIZED;

        return new JsonResponse(
            [
                'type' => 'about:blank',
                'title' => Response::$statusTexts[$status],
                'status' => $status,
                'detail' => $detail,
            ],
            $status,
            ['Content-Type' => 'application/problem+json; charset=utf-8'],
        );
    }
}
