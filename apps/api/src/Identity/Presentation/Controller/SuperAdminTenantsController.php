<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\SuperAdmin\SuperAdminContext;
use App\Identity\Application\SuperAdmin\SuperAdminTenantResponseBuilder;
use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Identity\Domain\Entity\User;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * RBAC-P5-019 (#709) + RBAC-P5-020 (#710) — Super Admin operator panel
 * endpoints for the tenant list + detail views.
 *
 *   - GET /api/admin/tenants
 *     Cross-tenant list of every tenant on the platform, metadata only.
 *
 *   - GET /api/admin/tenants/{id}
 *     Single-tenant detail with metadata + aggregate counters.
 *
 * Privacy boundary (PRD §11): the response shape exposes tenant
 * identity, plan, locale config, and an active-user counter. It NEVER
 * touches per-tenant domain rows (products, attributes, values) — those
 * stay invisible to Super Admin per the PIM privacy contract. The
 * audit subsystem stamps `cross_tenant_access=true` on every entry
 * produced by this controller via {@see SuperAdminContext}.
 *
 * Auth gate: the route requires the global `super_admin` role.
 * `RequiresPermission(module: 'user', action: 'admin')` keeps the same
 * legacy attribute the rest of Phase 5 uses + we add an explicit
 * super-admin role check on top (Phase 6 retrofit migrates onto PRD
 * `platform.tenants.list` / `platform.tenants.manage`).
 *
 * **Deployment topology note:** per #709 the long-term home for these
 * endpoints is the dedicated `admin.cortex.pl` subdomain with a
 * separate JWT cookie scope. That split is operator infra work
 * (Caddyfile + cookie domain config) and is not a blocker for the
 * functional substrate — the routes work today on the main domain
 * gated by the role check. The subdomain migration lands in a
 * follow-up deployment ticket without code changes here.
 */
final readonly class SuperAdminTenantsController
{
    public function __construct(
        private Security $security,
        private SuperAdminContext $superAdminContext,
        private TenantRepositoryInterface $tenants,
        private SuperAdminTenantResponseBuilder $builder,
    ) {
    }

    #[Route(path: '/api/admin/tenants', methods: ['GET'], name: 'api_admin_tenants_list')]
    #[RequiresPermission(module: 'user', action: 'admin')]
    public function list(): JsonResponse
    {
        $superAdminId = $this->requireSuperAdmin();
        if ($superAdminId instanceof JsonResponse) {
            return $superAdminId;
        }

        $rows = $this->superAdminContext->runCrossTenant(
            $superAdminId,
            fn (): array => $this->tenants->findAllOrderedByCode(),
        );
        $member = $this->builder->buildList($rows);

        return new JsonResponse([
            'member' => $member,
            'totalItems' => \count($member),
            'meta' => [
                'page' => 1,
                'per_page' => \count($member),
                'total_pages' => 1,
            ],
        ]);
    }

    #[Route(
        path: '/api/admin/tenants/{id}',
        methods: ['GET'],
        name: 'api_admin_tenants_detail',
        requirements: ['id' => '[0-9a-f-]{36}'],
    )]
    #[RequiresPermission(module: 'user', action: 'admin')]
    public function detail(string $id): JsonResponse
    {
        $superAdminId = $this->requireSuperAdmin();
        if ($superAdminId instanceof JsonResponse) {
            return $superAdminId;
        }

        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'Not Found', 'Tenant not found.');
        }

        $tenant = $this->superAdminContext->runCrossTenant(
            $superAdminId,
            fn () => $this->tenants->findById($uuid),
        );
        if (null === $tenant) {
            return $this->problem(Response::HTTP_NOT_FOUND, 'Not Found', 'Tenant not found.');
        }

        return new JsonResponse($this->builder->buildOne($tenant));
    }

    /**
     * Returns the active Super Admin's Uuid on success, or a 403 problem
     * response if the caller does not hold the `super_admin` role.
     */
    private function requireSuperAdmin(): Uuid|JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return $this->problem(Response::HTTP_UNAUTHORIZED, 'Unauthorized', 'No authenticated user.');
        }

        $hasSuperAdmin = false;
        foreach ($user->getAssignedRoles() as $role) {
            if (RbacMatrix::ROLE_SUPER_ADMIN === $role->getCode()) {
                $hasSuperAdmin = true;
                break;
            }
        }

        if (!$hasSuperAdmin) {
            return $this->problem(
                Response::HTTP_FORBIDDEN,
                'Forbidden',
                'Super Admin role required to access the operator panel.',
                ['code' => 'super_admin_required'],
            );
        }

        return $user->getId();
    }

    /**
     * @param array<string, mixed> $extras
     */
    private function problem(int $status, string $title, string $detail, array $extras = []): JsonResponse
    {
        return new JsonResponse(
            array_merge(
                [
                    'type' => 'about:blank',
                    'title' => $title,
                    'status' => $status,
                    'detail' => $detail,
                ],
                $extras,
            ),
            $status,
            ['Content-Type' => 'application/problem+json; charset=utf-8'],
        );
    }
}
