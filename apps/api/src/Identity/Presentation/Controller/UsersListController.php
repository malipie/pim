<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\UserListResponseBuilder;
use App\Identity\Domain\Attribute\RequiresPermission;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Repository\UserRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RBAC-P5-001 (#691) — `GET /api/users` listing for the Settings → Users
 * page. Returns the authenticated principal's tenant only; cross-tenant
 * isolation is enforced at the repository (`WHERE tenant = :tenant`) on
 * top of the Doctrine TenantFilter, so even a misconfigured filter cannot
 * leak rows.
 *
 * Response shape is Hydra-compatible so the existing admin data provider
 * (`apps/admin/src/lib/data-provider.ts`) reads the page without a custom
 * branch:
 *
 *   {
 *     "member":     [ user-projection, ... ],   // see UserListResponseBuilder
 *     "totalItems": <int>,                       // total in tenant after filters
 *     "meta": { "page": <int>, "per_page": <int>, "total_pages": <int> }
 *   }
 *
 * Query params:
 *   - `page` / `itemsPerPage` (Refine sends both names — accept either)
 *   - `status` — filter `User::STATUS_ACTIVE` / `User::STATUS_DISABLED`
 *   - `role[]` — list of Role UUIDs (M2M intersection)
 *   - `search` — case-insensitive substring on email
 */
final readonly class UsersListController
{
    private const int MAX_PER_PAGE = 50;
    private const int DEFAULT_PER_PAGE = 50;
    private const array ALLOWED_STATUSES = [User::STATUS_ACTIVE, User::STATUS_DISABLED];

    public function __construct(
        private Security $security,
        private UserRepositoryInterface $users,
        private UserListResponseBuilder $builder,
    ) {
    }

    #[Route(path: '/api/users', methods: ['GET'], name: 'api_users_list')]
    /*
     * Permission gate: `user.admin` from the seeded RbacMatrix until Phase 6
     * (#720+) retrofits all endpoints onto the PRD §3.2 codes (`settings.users.manage`).
     * `user.admin` is held only by super_admin in the current matrix — viewer
     * has `user.read` (read-only-on-everything pattern) so a narrower gate is
     * needed than the read flag.
     */
    #[RequiresPermission(module: 'user', action: 'admin')]
    public function __invoke(Request $request): JsonResponse
    {
        $principal = $this->security->getUser();
        if (!$principal instanceof User) {
            // The JWT firewall + EndpointGuardListener catch this earlier;
            // defence-in-depth in case a custom route bypasses both.
            return $this->unauthorized();
        }

        $tenant = $principal->getTenant();

        $page = max(1, (int) $request->query->get('page', '1'));
        $perPageRaw = (int) ($request->query->get('itemsPerPage') ?? $request->query->get('per_page') ?? self::DEFAULT_PER_PAGE);
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPageRaw));

        $status = $request->query->get('status');
        if (null !== $status && !\in_array($status, self::ALLOWED_STATUSES, true)) {
            $status = null;
        }

        $search = $request->query->get('search');
        if (null !== $search) {
            $search = trim($search);
            if ('' === $search) {
                $search = null;
            }
        }

        $roleIdsRaw = $request->query->all('role');
        $roleIds = [];
        foreach ($roleIdsRaw as $candidate) {
            if (\is_string($candidate) && '' !== $candidate) {
                $roleIds[] = $candidate;
            }
        }
        $roleIds = [] === $roleIds ? null : $roleIds;

        $users = $this->users->findAllByTenantPaginated($tenant, $status, $roleIds, $search, $page, $perPage);
        $total = $this->users->countByTenant($tenant, $status, $roleIds, $search);
        $totalPages = (int) ceil($total / $perPage);
        $totalPages = max(1, $totalPages);

        return new JsonResponse([
            'member' => $this->builder->buildList($users),
            'totalItems' => $total,
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    private function unauthorized(): JsonResponse
    {
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
}
