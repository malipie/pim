<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Application\TotpEnrolmentService;
use App\Identity\Domain\Attribute\NoPermissionRequired;
use App\Identity\Domain\Entity\User;
use DateTimeInterface;
use JsonException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use const JSON_THROW_ON_ERROR;

/**
 * 2FA TOTP enrolment endpoints (#0.11.1).
 *
 *   POST /api/auth/2fa/enrol    — provision a fresh secret + 10
 *                                  recovery codes (returned ONCE).
 *                                  Idempotent: replaces any pending
 *                                  enrolment that has not yet been
 *                                  confirmed; refuses to overwrite an
 *                                  ACTIVE 2FA setup.
 *   POST /api/auth/2fa/verify   — confirm the first authenticator code
 *                                  and flip 2FA on. Body: `{ code: "123456" }`.
 *   POST /api/auth/2fa/disable  — wipe the secret + codes after the
 *                                  user proves possession. Body:
 *                                  `{ code: "123456" }` (TOTP or backup).
 *
 * Login-flow integration (challenging the user for a 2FA code on
 * `/api/auth/login`) is a follow-up — these endpoints land first so
 * the persistence + service layer can be exercised independently.
 */
final readonly class TwoFactorController
{
    public function __construct(
        private Security $security,
        private TotpEnrolmentService $enrolment,
    ) {
    }

    #[Route(path: '/api/auth/2fa/enrol', methods: ['POST'], name: 'api_auth_2fa_enrol')]
    public function enrol(): Response
    {
        $user = self::requireUser($this->security);
        if ($user instanceof Response) {
            return $user;
        }

        if ($user->isTotpEnabled()) {
            return self::problem(
                Response::HTTP_CONFLICT,
                'TOTP already active',
                'Disable the existing 2FA setup before re-enrolling.',
            );
        }

        $payload = $this->enrolment->enrol($user);

        return new JsonResponse($payload, Response::HTTP_OK);
    }

    #[Route(path: '/api/auth/2fa/verify', methods: ['POST'], name: 'api_auth_2fa_verify')]
    public function verify(Request $request): Response
    {
        $user = self::requireUser($this->security);
        if ($user instanceof Response) {
            return $user;
        }

        $code = self::readCode($request);
        if (null === $code) {
            return self::problem(
                Response::HTTP_BAD_REQUEST,
                'Missing verification code',
                'Provide `code` in the JSON body.',
            );
        }

        if (!$this->enrolment->confirm($user, $code)) {
            return self::problem(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Invalid verification code',
                'The code did not match the pending enrolment.',
            );
        }

        return new JsonResponse([
            'enabled' => true,
            'enabled_at' => $user->getTotpEnabledAt()?->format(DateTimeInterface::ATOM),
        ]);
    }

    #[Route(path: '/api/auth/2fa/disable', methods: ['POST'], name: 'api_auth_2fa_disable')]
    public function disable(Request $request): Response
    {
        $user = self::requireUser($this->security);
        if ($user instanceof Response) {
            return $user;
        }

        if (!$user->isTotpEnabled()) {
            return self::problem(
                Response::HTTP_CONFLICT,
                'TOTP not active',
                '2FA is not enabled for this user.',
            );
        }

        $code = self::readCode($request);
        if (null === $code || !$this->enrolment->verify($user, $code)) {
            return self::problem(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Invalid verification code',
                'A valid TOTP or backup code is required to disable 2FA.',
            );
        }

        $this->enrolment->disable($user);

        return new JsonResponse(['enabled' => false]);
    }

    /**
     * RBAC-P5-013 (#703) — current MFA state for Profile → Security.
     *
     *   GET /api/me/mfa/status
     *   { enabled: bool, enabled_at: ATOM|null, backup_codes_remaining: int,
     *     enrolment_pending: bool }
     *
     * `enrolment_pending` is true when a secret has been minted but the
     * first authenticator code has not been verified yet — UI shows
     * "Resume enrolment" instead of "Enable" in that state.
     */
    #[Route(path: '/api/me/mfa/status', methods: ['GET'], name: 'api_me_mfa_status')]
    #[NoPermissionRequired(reason: 'Caller reads their own MFA state — `getUser()` enforces auth, no cross-user access.')]
    public function status(): Response
    {
        $user = self::requireUser($this->security);
        if ($user instanceof Response) {
            return $user;
        }

        return new JsonResponse([
            'enabled' => $user->isTotpEnabled(),
            'enabled_at' => $user->getTotpEnabledAt()?->format(DateTimeInterface::ATOM),
            'backup_codes_remaining' => \count($user->getTotpBackupCodes()),
            'enrolment_pending' => null !== $user->getTotpSecret() && !$user->isTotpEnabled(),
        ]);
    }

    /**
     * RBAC-P5-013 (#703) — rotate one-shot recovery codes for an
     * already-enrolled user. Body must include `code` (TOTP or
     * surviving backup code) to prove possession; the rotation
     * invalidates every previous code so a stolen list can't be reused.
     *
     *   POST /api/me/mfa/recovery-codes/regenerate
     *   { "code": "123456" }
     *
     * Response surfaces the new cleartext codes ONCE — same shape as
     * the `enrol` payload.
     */
    #[Route(
        path: '/api/me/mfa/recovery-codes/regenerate',
        methods: ['POST'],
        name: 'api_me_mfa_recovery_codes_regenerate',
    )]
    #[NoPermissionRequired(reason: 'Caller rotates their own recovery codes — TOTP/backup code proves possession.')]
    public function regenerateRecoveryCodes(Request $request): Response
    {
        $user = self::requireUser($this->security);
        if ($user instanceof Response) {
            return $user;
        }

        if (!$user->isTotpEnabled()) {
            return self::problem(
                Response::HTTP_CONFLICT,
                'TOTP not active',
                '2FA must be enabled before rotating recovery codes.',
            );
        }

        $code = self::readCode($request);
        if (null === $code || !$this->enrolment->verify($user, $code)) {
            return self::problem(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                'Invalid verification code',
                'A valid TOTP or backup code is required to rotate recovery codes.',
            );
        }

        $newCodes = $this->enrolment->rotateBackupCodes($user);

        return new JsonResponse(['backup_codes' => $newCodes]);
    }

    private static function requireUser(Security $security): User|Response
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            return self::problem(
                Response::HTTP_UNAUTHORIZED,
                'Unauthorized',
                'No authenticated user.',
            );
        }

        return $user;
    }

    private static function readCode(Request $request): ?string
    {
        $body = $request->getContent();
        if ('' === $body) {
            return null;
        }
        try {
            $payload = json_decode($body, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!\is_array($payload) || !isset($payload['code']) || !\is_string($payload['code'])) {
            return null;
        }
        $trimmed = trim($payload['code']);

        return '' === $trimmed ? null : $trimmed;
    }

    private static function problem(int $status, string $title, string $detail): JsonResponse
    {
        return new JsonResponse(
            [
                'type' => 'about:blank',
                'title' => $title,
                'status' => $status,
                'detail' => $detail,
            ],
            $status,
            ['Content-Type' => 'application/problem+json; charset=utf-8'],
        );
    }
}
