<?php

declare(strict_types=1);

namespace App\Import\Presentation\Controller;

use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Identity\Domain\Entity\User;
use App\Import\Application\Service\ImportRollbackService;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use DateTimeInterface;
use InvalidArgumentException;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * IMP-05 (#446) — wizard results screen "Wycofaj import" CTA.
 *
 * Owner-only, 24h window. The handler does the heavy lifting; this
 * controller is the HTTP edge: parse id, load + check ownership,
 * delegate, surface a clear error if the window expired or the
 * session isn't rollbackable.
 */
final class RollbackImportController
{
    public function __construct(
        private readonly ImportSessionRepositoryInterface $sessions,
        private readonly ImportRollbackService $rollbackService,
        private readonly Security $security,
    ) {
    }

    #[Route(
        path: '/api/import-sessions/{id}/rollback',
        name: 'imports_rollback',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['POST'],
    )]
    #[RequiresPermission(module: 'import_session', action: 'admin')]
    public function __invoke(string $id): JsonResponse
    {
        $session = $this->loadOwned($id);

        try {
            $result = $this->rollbackService->rollback($session);
        } catch (LogicException $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }

        $reload = $this->sessions->findById($session->getId()) ?? $session;

        return new JsonResponse([
            'id' => $reload->getId()->toRfc4122(),
            'status' => $reload->getStatus()->value,
            'rolled_back_at' => $reload->getRolledBackAt()?->format(DateTimeInterface::RFC3339_EXTENDED),
            'deleted_objects' => $result['deletedObjects'],
            'deleted_object_values' => $result['deletedValues'],
        ], Response::HTTP_OK);
    }

    private function loadOwned(string $rawId): ImportSession
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new UnauthorizedHttpException('JWT', 'Authenticated user required.');
        }

        try {
            $id = Uuid::fromString($rawId);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(\sprintf('Import session "%s" was not found.', $rawId));
        }

        $session = $this->sessions->findById($id);
        if (!$session instanceof ImportSession || $session->getUserId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            throw new NotFoundHttpException(\sprintf('Import session "%s" was not found.', $rawId));
        }

        return $session;
    }
}
