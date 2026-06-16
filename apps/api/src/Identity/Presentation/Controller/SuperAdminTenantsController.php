<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\SuperAdmin\PlatformOperatorGuard;
use App\Identity\Application\SuperAdmin\SuperAdminContext;
use App\Identity\Application\SuperAdmin\SuperAdminTenantResponseBuilder;
use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Identity\Domain\Rbac\RbacMatrix;
use App\Shared\Domain\Repository\TenantRepositoryInterface;
use InvalidArgumentException;
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
 * Auth gate (AUD-003 #1575): the routes require the cross-tenant
 * `platform.tenants.manage` permission, held ONLY by the dedicated
 * `platform_operator` role — never by a tenant Owner's `super_admin`.
 * Both the `#[RequiresPermission]` attribute (enforced by the
 * EndpointGuardListener) and the in-controller {@see PlatformOperatorGuard}
 * check that permission, so neither a forgotten attribute nor a future
 * listener change can re-open the panel to ordinary Owners.
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
        private PlatformOperatorGuard $guard,
        private SuperAdminContext $superAdminContext,
        private TenantRepositoryInterface $tenants,
        private SuperAdminTenantResponseBuilder $builder,
    ) {
    }

    #[Route(path: '/api/admin/tenants', methods: ['GET'], name: 'api_admin_tenants_list')]
    #[RequiresPermission(module: 'platform.tenants', action: 'manage')]
    public function list(): JsonResponse
    {
        $superAdminId = $this->guard->require(RbacMatrix::PERMISSION_PLATFORM_TENANTS_MANAGE)->getId();

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
    #[RequiresPermission(module: 'platform.tenants', action: 'manage')]
    public function detail(string $id): JsonResponse
    {
        $superAdminId = $this->guard->require(RbacMatrix::PERMISSION_PLATFORM_TENANTS_MANAGE)->getId();

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
