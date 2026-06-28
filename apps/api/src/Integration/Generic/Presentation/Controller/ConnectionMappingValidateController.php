<?php

declare(strict_types=1);

namespace App\Integration\Generic\Presentation\Controller;

use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Integration\Generic\Application\Mapping\MappingValidator;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Repository\ConnectionRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * APIC-P2-08 (ADR-0022) — validates a connection's field mappings: returns the
 * blocking errors (e.g. a missing match key for inbound) and the non-fatal type
 * warnings so the mapping screen can surface them before a sync runs.
 *
 * The connection is resolved tenant-scoped (Postgres RLS), so a cross-tenant id
 * is a 404; `settings.integrations.manage` gates the action. Always HTTP 200 —
 * the *validation ran*; whether the mappings are valid lives in the body.
 */
final class ConnectionMappingValidateController
{
    public function __construct(
        private readonly ConnectionRepositoryInterface $connections,
        private readonly MappingValidator $validator,
    ) {
    }

    #[Route(
        path: '/api/connections/{id}/mappings/validate',
        name: 'integration_connection_mappings_validate',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['POST'],
    )]
    #[RequiresPermission(module: 'settings.integrations', action: 'manage')]
    public function __invoke(string $id): JsonResponse
    {
        $connection = $this->requireConnection($id);
        $result = $this->validator->validate($connection);

        return new JsonResponse([
            'valid' => $result->isValid(),
            'errors' => $result->errors,
            'warnings' => array_map(
                static fn ($warning): array => [
                    'pimTarget' => $warning->pimTarget,
                    'remoteFieldPath' => $warning->remoteFieldPath,
                    'message' => $warning->message,
                ],
                $result->warnings,
            ),
        ], Response::HTTP_OK);
    }

    private function requireConnection(string $id): Connection
    {
        try {
            $connectionId = Uuid::fromString($id);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(\sprintf('Connection "%s" was not found.', $id));
        }

        $connection = $this->connections->findById($connectionId);
        if (!$connection instanceof Connection) {
            throw new NotFoundHttpException(\sprintf('Connection "%s" was not found.', $id));
        }

        return $connection;
    }
}
