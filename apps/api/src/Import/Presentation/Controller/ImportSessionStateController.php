<?php

declare(strict_types=1);

namespace App\Import\Presentation\Controller;

use App\Identity\Domain\Entity\User;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
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
 * Wizard controls — pause / resume / cancel for an in-flight import.
 *
 * State changes are authoritative on the {@see ImportSession} entity;
 * the worker (`ImportRunHandler`) inspects the row between batches in
 * a follow-up to honour pause/cancel mid-run. In MVP the operator
 * sees the response immediately and the running batch finishes the
 * current chunk before stopping.
 */
final class ImportSessionStateController
{
    public function __construct(
        private readonly ImportSessionRepositoryInterface $sessions,
        private readonly Security $security,
    ) {
    }

    #[Route(
        path: '/api/import-sessions/{id}/pause',
        name: 'imports_pause',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['POST'],
    )]
    public function pause(string $id): JsonResponse
    {
        $session = $this->loadOwned($id);
        try {
            $session->markPaused();
        } catch (LogicException $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }
        $this->sessions->save($session);

        return $this->respond($session);
    }

    #[Route(
        path: '/api/import-sessions/{id}/resume',
        name: 'imports_resume',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['POST'],
    )]
    public function resume(string $id): JsonResponse
    {
        $session = $this->loadOwned($id);
        try {
            $session->markRunning();
        } catch (LogicException $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }
        $this->sessions->save($session);

        return $this->respond($session);
    }

    #[Route(
        path: '/api/import-sessions/{id}/cancel',
        name: 'imports_cancel',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['POST'],
    )]
    public function cancel(string $id): JsonResponse
    {
        $session = $this->loadOwned($id);
        try {
            $session->markCancelled();
        } catch (LogicException $exception) {
            throw new ConflictHttpException($exception->getMessage());
        }
        $this->sessions->save($session);

        return $this->respond($session);
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

    private function respond(ImportSession $session): JsonResponse
    {
        return new JsonResponse([
            'id' => $session->getId()->toRfc4122(),
            'status' => $session->getStatus()->value,
        ], Response::HTTP_OK);
    }
}
