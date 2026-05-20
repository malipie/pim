<?php

declare(strict_types=1);

namespace App\Import\Presentation\Controller;

use App\Identity\Domain\Attribute\RequiresPermission;
use App\Identity\Domain\Entity\User;
use App\Import\Application\Service\ImportThroughputCalculator;
use DateTimeInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * VIEW-IMP-01 — live throughput probe for the sessions hero card.
 *
 * Returns the per-second processing rate aggregated over the
 * operator's currently active sessions, computed from existing
 * counters (no new schema). Polled by the FE every ~5s while a
 * session is in flight.
 */
final class ImportThroughputController
{
    public function __construct(
        private readonly ImportThroughputCalculator $calculator,
        private readonly Security $security,
    ) {
    }

    #[Route(
        path: '/api/import-sessions/throughput',
        name: 'imports_throughput',
        methods: ['GET'],
    )]
    #[RequiresPermission(module: 'import_session', action: 'read')]
    public function __invoke(Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new UnauthorizedHttpException('JWT', 'Authenticated user required.');
        }

        $windowMin = (int) $request->query->get('windowMin', '5');
        if ($windowMin < 1 || $windowMin > 60) {
            throw new BadRequestHttpException('windowMin must be between 1 and 60.');
        }

        $snapshot = $this->calculator->calculate(
            tenant: $user->getTenant(),
            userId: $user->getId(),
            windowMin: $windowMin,
        );

        return new JsonResponse([
            'rows_per_sec' => round($snapshot->rowsPerSec, 2),
            'active_sessions' => $snapshot->activeSessions,
            'window_min' => $snapshot->windowMin,
            'sampled_at' => $snapshot->sampledAt->format(DateTimeInterface::RFC3339_EXTENDED),
        ], Response::HTTP_OK);
    }
}
