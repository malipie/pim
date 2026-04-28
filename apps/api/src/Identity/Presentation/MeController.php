<?php

declare(strict_types=1);

namespace App\Identity\Presentation;

use App\Identity\Domain\Entity\User;
use DateTimeInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * GET /api/auth/me — return the principal currently authenticated by the JWT.
 *
 * Used by the admin SPA on bootstrap to populate the user menu and decide
 * which sidebar entries are reachable, and by integration clients smoke-
 * testing their token. The shape stays minimal — id, email, the resolved
 * role list, the tenant header (code + name), and `last_login_at` — so the
 * payload doubles as the boot manifest without leaking permission internals.
 */
final readonly class MeController
{
    public function __construct(
        private Security $security,
    ) {
    }

    #[Route(path: '/api/auth/me', methods: ['GET'], name: 'api_auth_me')]
    public function __invoke(): Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            // The JWT firewall guards `/api`, so reaching this controller
            // without a User principal would only happen in a misconfigured
            // environment — fall back to 401 rather than 500.
            return new JsonResponse(
                [
                    'type' => 'about:blank',
                    'title' => 'Unauthorized',
                    'status' => Response::HTTP_UNAUTHORIZED,
                    'detail' => 'No authenticated user.',
                ],
                Response::HTTP_UNAUTHORIZED,
                ['Content-Type' => 'application/problem+json; charset=utf-8'],
            );
        }

        $tenant = $user->getTenant();

        return new JsonResponse([
            'id' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'tenant' => [
                'id' => $tenant->getId()->toRfc4122(),
                'code' => $tenant->getCode(),
                'name' => $tenant->getName(),
            ],
            'last_login_at' => $user->getLastLoginAt()?->format(DateTimeInterface::ATOM),
        ]);
    }
}
