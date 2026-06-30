<?php

declare(strict_types=1);

namespace App\Integration\Generic\Presentation\Controller;

use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Integration\Generic\Application\Schedule\SyncScheduleDispatcher;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Repository\SyncBindingRepositoryInterface;
use DateTimeInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * APIC-P3-10 (ADR-0022) — operator actions on a sync binding that don't fit the
 * REST CRUD verbs (CQRS procedural endpoints, ADR-0020):
 *   - `POST /api/sync_bindings/{id}/run`    — fire the binding's leg(s) now.
 *   - `POST /api/sync_bindings/{id}/pause`  — disable + clear the next run.
 *   - `POST /api/sync_bindings/{id}/resume` — re-enable + reschedule.
 *
 * The binding is resolved tenant-scoped (Postgres RLS), so a cross-tenant id is
 * a 404; the `settings.integrations.manage` permission gates every action.
 */
final class SyncBindingActionsController
{
    private const string ID_REQUIREMENT = '[0-9a-fA-F-]{36}';

    public function __construct(
        private readonly SyncBindingRepositoryInterface $bindings,
        private readonly SyncScheduleDispatcher $dispatcher,
    ) {
    }

    #[Route(
        path: '/api/sync_bindings/{id}/run',
        name: 'integration_sync_binding_run',
        requirements: ['id' => self::ID_REQUIREMENT],
        methods: ['POST'],
    )]
    #[RequiresPermission(module: 'settings.integrations', action: 'manage')]
    public function run(string $id, Request $request): JsonResponse
    {
        $binding = $this->require($id);
        // `?dry_run=1` previews the outbound push (builds + logs payloads, no
        // remote call) — a guard against flooding the external shop (#1889).
        $dryRun = $request->query->getBoolean('dry_run');

        $this->dispatcher->dispatch($binding, $dryRun);

        return new JsonResponse([
            'dispatched' => true,
            'dry_run' => $dryRun,
            'direction' => $binding->getDirection()->value,
            'next_run' => $binding->getNextRun()?->format(DateTimeInterface::RFC3339_EXTENDED),
        ], Response::HTTP_ACCEPTED);
    }

    #[Route(
        path: '/api/sync_bindings/{id}/pause',
        name: 'integration_sync_binding_pause',
        requirements: ['id' => self::ID_REQUIREMENT],
        methods: ['POST'],
    )]
    #[RequiresPermission(module: 'settings.integrations', action: 'manage')]
    public function pause(string $id): JsonResponse
    {
        $binding = $this->require($id);

        $binding->setEnabled(false);
        $binding->setNextRun(null);
        $this->bindings->save($binding);

        return new JsonResponse(['enabled' => false, 'next_run' => null], Response::HTTP_OK);
    }

    #[Route(
        path: '/api/sync_bindings/{id}/resume',
        name: 'integration_sync_binding_resume',
        requirements: ['id' => self::ID_REQUIREMENT],
        methods: ['POST'],
    )]
    #[RequiresPermission(module: 'settings.integrations', action: 'manage')]
    public function resume(string $id): JsonResponse
    {
        $binding = $this->require($id);

        $binding->setEnabled(true);
        $this->bindings->save($binding);
        $this->dispatcher->computeNextRun($binding);

        return new JsonResponse([
            'enabled' => true,
            'next_run' => $binding->getNextRun()?->format(DateTimeInterface::RFC3339_EXTENDED),
        ], Response::HTTP_OK);
    }

    private function require(string $id): SyncBinding
    {
        try {
            $bindingId = Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(\sprintf('SyncBinding "%s" was not found.', $id));
        }

        $binding = $this->bindings->findById($bindingId);
        if (!$binding instanceof SyncBinding) {
            throw new NotFoundHttpException(\sprintf('SyncBinding "%s" was not found.', $id));
        }

        return $binding;
    }
}
