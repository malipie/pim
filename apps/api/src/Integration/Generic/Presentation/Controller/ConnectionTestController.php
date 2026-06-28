<?php

declare(strict_types=1);

namespace App\Integration\Generic\Presentation\Controller;

use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Enum\ConnectionStatus;
use App\Integration\Generic\Domain\Exception\RemoteRequestFailedException;
use App\Integration\Generic\Domain\Exception\SsrfBlockedException;
use App\Integration\Generic\Domain\Repository\ConnectionRepositoryInterface;
use App\Integration\Generic\Infrastructure\Http\GenericRestClient;
use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * APIC-P1-05 (ADR-0022) — probes a connection's base URL through the SSRF-safe
 * {@see GenericRestClient} and records the outcome on the entity.
 *
 * The probe response telemetry (status / latency / size / content-type /
 * truncated sample) is returned with HTTP 200 even when the remote failed —
 * the *test action* succeeded; its *result* lives in the body (`ok`). The
 * connection is resolved tenant-scoped (Postgres RLS), so a cross-tenant id is
 * a 404, and the `settings.integrations.manage` permission gates the action.
 */
final class ConnectionTestController
{
    private const int SAMPLE_MAX_CHARS = 2000;

    public function __construct(
        private readonly ConnectionRepositoryInterface $connections,
        private readonly GenericRestClient $client,
    ) {
    }

    #[Route(
        path: '/api/connections/{id}/test',
        name: 'integration_connection_test',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['POST'],
    )]
    #[RequiresPermission(module: 'settings.integrations', action: 'manage')]
    public function __invoke(string $id): JsonResponse
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

        try {
            $response = $this->client->request($connection, 'GET', $connection->getBaseUrl());
        } catch (SsrfBlockedException|RemoteRequestFailedException $exception) {
            return $this->finish($connection, ConnectionStatus::Error, [
                'ok' => false,
                'error' => $exception->getMessage(),
            ]);
        }

        return $this->finish(
            $connection,
            $response->isSuccessful() ? ConnectionStatus::Active : ConnectionStatus::Error,
            [
                'ok' => $response->isSuccessful(),
                'http_status' => $response->statusCode,
                'latency_ms' => $response->durationMs,
                'size_bytes' => $response->sizeBytes,
                'content_type' => $response->contentType(),
                'sample' => mb_substr($response->body, 0, self::SAMPLE_MAX_CHARS),
            ],
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function finish(Connection $connection, ConnectionStatus $status, array $payload): JsonResponse
    {
        $checkedAt = new DateTimeImmutable();
        $connection->recordHealthCheck($checkedAt, $status);
        $this->connections->save($connection);

        $payload['status'] = $status->value;
        $payload['checked_at'] = $checkedAt->format(DateTimeInterface::RFC3339_EXTENDED);

        return new JsonResponse($payload, Response::HTTP_OK);
    }
}
