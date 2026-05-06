<?php

declare(strict_types=1);

namespace App\Import\Presentation\Controller;

use App\Identity\Domain\Entity\User;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use DateTimeInterface;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Read-only endpoint surfacing the live counts + status the wizard's
 * progress / results screens poll between Mercure events.
 */
final class GetImportSessionController
{
    public function __construct(
        private readonly ImportSessionRepositoryInterface $sessions,
        private readonly Security $security,
    ) {
    }

    #[Route(
        path: '/api/import-sessions/{id}',
        name: 'imports_show',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['GET'],
    )]
    public function __invoke(string $id): JsonResponse
    {
        $session = $this->loadSession($id);

        return new JsonResponse([
            'id' => $session->getId()->toRfc4122(),
            'status' => $session->getStatus()->value,
            'file_name' => $session->getFileName(),
            'total_rows' => $session->getTotalRows(),
            'success_count' => $session->getSuccessCount(),
            'error_count' => $session->getErrorCount(),
            'images_downloaded' => $session->getImagesDownloaded(),
            'images_failed' => $session->getImagesFailed(),
            'started_at' => $session->getStartedAt()?->format(DateTimeInterface::RFC3339_EXTENDED),
            'completed_at' => $session->getCompletedAt()?->format(DateTimeInterface::RFC3339_EXTENDED),
            'rollback_until' => $session->getRollbackUntil()?->format(DateTimeInterface::RFC3339_EXTENDED),
            'rolled_back_at' => $session->getRolledBackAt()?->format(DateTimeInterface::RFC3339_EXTENDED),
            'error_message' => $session->getErrorMessage(),
        ], Response::HTTP_OK);
    }

    private function loadSession(string $rawId): ImportSession
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
        if (!$session instanceof ImportSession) {
            throw new NotFoundHttpException(\sprintf('Import session "%s" was not found.', $rawId));
        }
        if ($session->getUserId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            throw new NotFoundHttpException(\sprintf('Import session "%s" was not found.', $rawId));
        }

        return $session;
    }
}
