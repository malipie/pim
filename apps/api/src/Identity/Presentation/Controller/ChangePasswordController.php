<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Contracts\Attribute\NoPermissionRequired;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RBAC-P5-012 (#702) — `POST /api/me/change-password`.
 *
 * Lets the authenticated principal swap their password by proving they
 * know the current one. The current_password re-auth is the defence
 * against a session-hijack attacker — stealing a JWT alone is not
 * enough to lock the rightful owner out of the account.
 *
 * Request body:
 *   {
 *     "current_password": "string (required)",
 *     "new_password":     "string (required, min 12 chars)"
 *   }
 *
 * Responses:
 *   - 204 No Content — password updated.
 *   - 400 Problem Details — payload missing or new_password too short.
 *   - 401 Problem Details — current_password incorrect.
 *
 * Open scope (deferred to a follow-up so #702 ships): revoking the
 * caller's refresh-token family. JWT TTL is short (1h) and the FE
 * client wipes its local token + forces re-login after a successful
 * change, so the practical exposure window is small. The dedicated
 * `RefreshTokenRepositoryInterface::revokeAllForUser()` method lands
 * with the session-management UI in a later ticket.
 */
final readonly class ChangePasswordController
{
    private const int MIN_LENGTH = 12;

    public function __construct(
        private Security $security,
        private UserRepositoryInterface $users,
        private UserPasswordHasherInterface $hasher,
    ) {
    }

    #[Route(path: '/api/me/change-password', methods: ['POST'], name: 'api_me_change_password')]
    #[NoPermissionRequired(reason: 'Authenticated user changing their own password — re-auth via current_password takes the place of the RBAC gate.')]
    public function __invoke(Request $request): Response
    {
        $principal = $this->security->getUser();
        if (!$principal instanceof User) {
            return $this->problem(Response::HTTP_UNAUTHORIZED, 'Unauthorized', 'No authenticated user.');
        }

        /** @var array<string, mixed>|null $payload */
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'Bad Request', 'Request body must be JSON.');
        }

        $current = $payload['current_password'] ?? null;
        $next = $payload['new_password'] ?? null;
        if (!\is_string($current) || !\is_string($next)) {
            return $this->problem(Response::HTTP_BAD_REQUEST, 'Bad Request', 'current_password and new_password are required strings.');
        }

        if (mb_strlen($next) < self::MIN_LENGTH) {
            return $this->problem(
                Response::HTTP_BAD_REQUEST,
                'Bad Request',
                \sprintf('new_password must be at least %d characters.', self::MIN_LENGTH),
                ['min_length' => self::MIN_LENGTH],
            );
        }

        if (!$this->hasher->isPasswordValid($principal, $current)) {
            return $this->problem(Response::HTTP_UNAUTHORIZED, 'Unauthorized', 'Invalid current password.');
        }

        $newHash = $this->hasher->hashPassword($principal, $next);
        $principal->changePassword($newHash);
        $this->users->save($principal);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * @param array<string, mixed> $extras
     */
    private function problem(int $status, string $title, string $detail, array $extras = []): JsonResponse
    {
        $body = array_merge(
            [
                'type' => 'about:blank',
                'title' => $title,
                'status' => $status,
                'detail' => $detail,
            ],
            $extras,
        );

        return new JsonResponse(
            $body,
            $status,
            ['Content-Type' => 'application/problem+json; charset=utf-8'],
        );
    }
}
