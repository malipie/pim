<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\PasswordResetService;
use App\Identity\Contracts\Attribute\NoPermissionRequired;
use LogicException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RBAC-P2-009 (#658) — password reset endpoints.
 *
 * POST /api/auth/password-reset/request — always 200 (account enumeration prevention)
 * POST /api/auth/password-reset/confirm — consume token + set new password
 *
 * Dev mode: request() returns plaintext token in response body so the
 * operator can test confirm(). Production removes this field once
 * Symfony Mailer infra ships and the token goes via email.
 */
final class PasswordResetController extends AbstractController
{
    public function __construct(
        private readonly PasswordResetService $service,
    ) {
    }

    #[Route(path: '/api/auth/password-reset/request', methods: ['POST'], name: 'api_auth_password_reset_request')]
    #[NoPermissionRequired(reason: 'Public reset request — initiator does not yet have a session; account-enumeration prevented by always-200 response.')]
    public function request(Request $request): JsonResponse
    {
        /** @var array{email?: string} $payload */
        $payload = (array) json_decode($request->getContent(), true);
        $email = $payload['email'] ?? '';
        if ('' === $email) {
            throw new BadRequestHttpException('email is required.');
        }

        $plaintext = $this->service->request($email);

        // ALWAYS return success — account-enumeration prevention. The
        // plaintext is exposed in dev-mode response only because the
        // mailer infra is not yet shipped; production drops this field.
        return new JsonResponse([
            'status' => 'sent',
            'token_dev_only' => $plaintext, // null when email not found
        ]);
    }

    #[Route(path: '/api/auth/password-reset/confirm', methods: ['POST'], name: 'api_auth_password_reset_confirm', requirements: ['_format' => 'json'])]
    #[NoPermissionRequired(reason: 'Public confirm — token IS the auth factor; no session required.')]
    public function confirm(Request $request): JsonResponse
    {
        /** @var array{token?: string, password?: string} $payload */
        $payload = (array) json_decode($request->getContent(), true);
        $token = $payload['token'] ?? '';
        $password = $payload['password'] ?? '';
        if ('' === $token || \strlen($password) < 8) {
            throw new BadRequestHttpException('token + password (min 8 chars) required.');
        }

        try {
            $user = $this->service->confirm($token, $password);
        } catch (RuntimeException $e) {
            throw new NotFoundHttpException($e->getMessage(), $e);
        } catch (LogicException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        return new JsonResponse([
            'user_id' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'status' => 'password-updated',
        ]);
    }
}
