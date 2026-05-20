<?php

declare(strict_types=1);

namespace App\Import\Presentation\Controller;

use App\Identity\Domain\Attribute\RequiresPermission;
use App\Identity\Domain\Entity\User;
use App\Import\Domain\Entity\ImportSchedule;
use App\Import\Domain\Repository\ImportScheduleRepositoryInterface;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * VIEW-IMP-04 (#502) — feeds the `NextRunsTimeline` widget.
 *
 * Returns the schedules whose `nextRun` falls within the next N hours
 * (default 24, capped at 168 = 1 week), sorted ascending. Tenant-scoped.
 */
final class UpcomingSchedulesController
{
    #[Route(
        path: '/api/import-schedules/upcoming',
        name: 'imports_schedule_upcoming',
        methods: ['GET'],
    )]
    #[RequiresPermission(module: 'import_schedule', action: 'read')]
    public function __invoke(
        Request $request,
        ImportScheduleRepositoryInterface $schedules,
        Security $security,
    ): JsonResponse {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw new UnauthorizedHttpException('JWT', 'Authenticated user required.');
        }
        $hours = (int) $request->query->get('hours', '24');
        if ($hours < 1 || $hours > 168) {
            throw new BadRequestHttpException('hours must be between 1 and 168.');
        }
        $until = new DateTimeImmutable('now', new DateTimeZone('UTC'))->modify(\sprintf('+%d hours', $hours));
        $items = array_map(
            static fn (ImportSchedule $s): array => [
                'id' => $s->getId()->toRfc4122(),
                'name' => $s->getName(),
                'code' => $s->getCode(),
                'priority' => $s->getPriority()->value,
                'next_run' => $s->getNextRun()?->format(DateTimeInterface::RFC3339_EXTENDED),
            ],
            $schedules->findUpcoming($user->getTenant(), $until),
        );

        return new JsonResponse([
            'member' => $items,
            'totalItems' => \count($items),
            'horizonHours' => $hours,
        ], Response::HTTP_OK);
    }
}
