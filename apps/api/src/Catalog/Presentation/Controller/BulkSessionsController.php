<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\Bulk\BulkRollbackHandler;
use App\Catalog\Domain\Entity\BulkLog;
use App\Catalog\Domain\Entity\BulkSession;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-17 (#544) — bulk session rollback + diff viewer endpoints.
 *
 * `POST /api/bulk-sessions/{id}/rollback` invokes the executor; the
 * toast in `RollbackToast` calls this when the operator clicks
 * *„Wycofaj"*. `GET /api/bulk-sessions/{id}` returns session details
 * + log entries for the session-viewer drawer.
 */
final class BulkSessionsController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BulkRollbackHandler $rollbackHandler,
    ) {
    }

    #[Route('/api/bulk-sessions/{id}', name: 'pim_bulk_session_show', requirements: ['id' => self::UUID_REGEX], methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function show(string $id): JsonResponse
    {
        $session = $this->loadSession($id);

        $logs = $this->em->getRepository(BulkLog::class)
            ->createQueryBuilder('l')
            ->where('l.bulkSessionId = :s')
            ->setParameter('s', $session->getId())
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults(1000)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'id' => $session->getId()->toRfc4122(),
            'action_type' => $session->getActionType(),
            'target_count' => $session->getTargetCount(),
            'success_count' => $session->getSuccessCount(),
            'skipped_count' => $session->getSkippedCount(),
            'error_count' => $session->getErrorCount(),
            'started_at' => $session->getStartedAt()->format(DateTimeInterface::ATOM),
            'completed_at' => $session->getCompletedAt()?->format(DateTimeInterface::ATOM),
            'rollback_available_until' => $session->getRollbackAvailableUntil()?->format(DateTimeInterface::ATOM),
            'rolled_back_at' => $session->getRolledBackAt()?->format(DateTimeInterface::ATOM),
            'is_rollback_available' => $session->isRollbackAvailable(),
            'source' => $session->getSource(),
            'logs' => array_map(
                static fn (BulkLog $l): array => [
                    'object_id' => $l->getObjectId()->toRfc4122(),
                    'old_value' => $l->getOldValue(),
                    'new_value' => $l->getNewValue(),
                    'level' => $l->getLevel(),
                    'message' => $l->getMessage(),
                ],
                $logs,
            ),
        ]);
    }

    #[Route('/api/bulk-sessions/{id}/rollback', name: 'pim_bulk_session_rollback', requirements: ['id' => self::UUID_REGEX], methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function rollback(string $id): JsonResponse
    {
        $session = $this->loadSession($id);
        $restored = $this->rollbackHandler->rollback($session);

        return new JsonResponse([
            'restored' => $restored,
            'rolled_back_at' => $session->getRolledBackAt()?->format(DateTimeInterface::ATOM),
        ], Response::HTTP_OK);
    }

    private function loadSession(string $id): BulkSession
    {
        $session = $this->em->getRepository(BulkSession::class)->find(Uuid::fromString($id));
        if (!$session instanceof BulkSession) {
            throw new NotFoundHttpException(\sprintf('Bulk session %s not found.', $id));
        }

        return $session;
    }
}
