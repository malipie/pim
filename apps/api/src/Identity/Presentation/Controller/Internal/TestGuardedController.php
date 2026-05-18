<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller\Internal;

use App\Identity\Domain\Attribute\NoPermissionRequired;
use App\Identity\Domain\Attribute\RequiresPermission;
use Symfony\Component\DependencyInjection\Attribute\When;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * RBAC-P3-001 (#664) — dev/test-only controller used by EndpointGuard
 * integration tests. Two routes:
 *   - `/api/_test/guarded` — requires `object.delete`, which is granted
 *     to super_admin in the matrix but NOT to viewer/marketing/other
 *     read-only roles, so it exercises the guard's denied branch.
 *   - `/api/_test/public` — carries `#[NoPermissionRequired]` so the
 *     guard's allow branch is also covered.
 *
 * `#[When]` keeps the service (and therefore the routes) out of the prod
 * container; production routes never expose this controller.
 */
#[When(env: 'dev')]
#[When(env: 'test')]
final class TestGuardedController
{
    #[Route(path: '/api/_test/guarded', methods: ['GET'], name: 'api_internal_test_guarded')]
    #[RequiresPermission(module: 'object', action: 'delete')]
    public function guarded(): JsonResponse
    {
        return new JsonResponse(['ok' => true, 'guard' => 'passed']);
    }

    #[Route(path: '/api/_test/public', methods: ['GET'], name: 'api_internal_test_public')]
    #[NoPermissionRequired(reason: 'EndpointGuard integration fixture — public branch.')]
    public function publicEndpoint(): JsonResponse
    {
        return new JsonResponse(['ok' => true, 'guard' => 'skipped']);
    }
}
