<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\InvitationService;
use App\Identity\Domain\Attribute\RequiresPermission;
use App\Identity\Domain\Entity\User;
use App\Shared\Application\TenantContext;
use LogicException;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use const DATE_ATOM;

/**
 * RBAC-P2-008 (#657) — magic-link invitation endpoints.
 *
 * POST /api/invitations         — admin/owner creates invitation
 * POST /api/invitations/{token}/accept — accept invitation (public)
 *
 * Dev mode: \`create\` returns the plaintext token in response body so the
 * operator can copy/paste into the accept call. Production email send
 * (Symfony Mailer + Twig template) is a follow-up ticket once mailer
 * infra ships.
 */
final class InvitationController extends AbstractController
{
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[Route(path: '/api/invitations', methods: ['POST'], name: 'api_invitations_create')]
    #[RequiresPermission(module: 'settings', action: 'users.manage')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function create(Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('Tenant context required.');
        }

        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new BadRequestHttpException('User principal required (JWT auth).');
        }

        /** @var array{email?: string, role_code?: string} $payload */
        $payload = (array) json_decode($request->getContent(), true);
        $email = $payload['email'] ?? '';
        $roleCode = $payload['role_code'] ?? '';
        if ('' === $email || '' === $roleCode) {
            throw new BadRequestHttpException('email and role_code are required.');
        }

        try {
            $result = $this->invitations->create($tenant, $email, $roleCode, $user);
        } catch (RuntimeException $e) {
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        return new JsonResponse([
            'invitation_id' => $result['invitation']->getId()->toRfc4122(),
            'email' => $result['invitation']->getEmail(),
            'expires_at' => $result['invitation']->getExpiresAt()->format(DATE_ATOM),
            // Dev-mode: token shipped in response so operator can test
            // accept flow before mailer infra ships. Production removes
            // this field and emails it via the Mailer follow-up.
            'token_dev_only' => $result['token'],
        ], 201);
    }

    #[Route(path: '/api/invitations/{token}/accept', methods: ['POST'], name: 'api_invitations_accept', requirements: ['token' => '[a-f0-9]{64}'])]
    #[\App\Identity\Domain\Attribute\NoPermissionRequired(reason: 'Magic-link accept is open by design — token IS the auth factor; account does not exist yet.')]
    public function accept(string $token, Request $request): JsonResponse
    {
        /** @var array{password?: string} $payload */
        $payload = (array) json_decode($request->getContent(), true);
        $password = $payload['password'] ?? '';
        if ('' === $password || \strlen($password) < 8) {
            throw new BadRequestHttpException('password (min 8 chars) is required.');
        }

        try {
            $user = $this->invitations->accept($token, $password);
        } catch (LogicException $e) {
            // already-accepted / revoked / expired (Invitation::accept enforces)
            throw new BadRequestHttpException($e->getMessage(), $e);
        }

        return new JsonResponse([
            'user_id' => $user->getId()->toRfc4122(),
            'email' => $user->getEmail(),
            'status' => 'accepted',
        ], 201);
    }
}
