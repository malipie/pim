<?php

declare(strict_types=1);

namespace App\Integration\Generic\Presentation\Controller;

use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Integration\Generic\Application\Discovery\SchemaDiscoveryService;
use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Exception\RemoteRequestFailedException;
use App\Integration\Generic\Domain\Exception\SsrfBlockedException;
use App\Integration\Generic\Domain\Repository\ConnectionRepositoryInterface;
use App\Integration\Generic\Domain\Repository\RemoteEndpointRepositoryInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * APIC-P2-05 (ADR-0022) — triggers schema discovery for one of a connection's
 * read endpoints: samples its first page and returns the proposed fields.
 *
 * The connection and endpoint are resolved tenant-scoped (Postgres RLS), so a
 * cross-tenant id is a 404; an endpoint that belongs to a different connection
 * is also a 404. The `settings.integrations.manage` permission gates the
 * action (firewall 401 + guard 403 + RLS 404). Proposals are not persisted —
 * the wizard accepts/edits, then the RemoteField CRUD saves them.
 */
final class ConnectionDiscoverController
{
    public function __construct(
        private readonly ConnectionRepositoryInterface $connections,
        private readonly RemoteEndpointRepositoryInterface $endpoints,
        private readonly SchemaDiscoveryService $discovery,
    ) {
    }

    #[Route(
        path: '/api/connections/{id}/discover',
        name: 'integration_connection_discover',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['POST'],
    )]
    #[RequiresPermission(module: 'settings.integrations', action: 'manage')]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $connection = $this->requireConnection($id);
        $endpoint = $this->requireEndpoint($request, $connection);

        try {
            $result = $this->discovery->discover($connection, $endpoint);
        } catch (SsrfBlockedException|RemoteRequestFailedException $exception) {
            // A blocked/unreachable remote is a user-side configuration problem,
            // not a server error — surface it as 422 with the reason.
            throw new UnprocessableEntityHttpException($exception->getMessage(), $exception);
        }

        $fields = array_map(
            static fn ($field): array => [
                'path' => $field->path,
                'dataType' => $field->dataType->value,
                'sampleValue' => $field->sampleValue,
            ],
            $result->fields,
        );

        return new JsonResponse([
            'fields' => $fields,
            'sampleRecord' => $result->sampleRecord,
            'sampledRecords' => $result->sampledRecords,
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

    private function requireEndpoint(Request $request, Connection $connection): RemoteEndpoint
    {
        $payload = json_decode($request->getContent(), true);
        $rawId = \is_array($payload) ? ($payload['endpoint'] ?? null) : null;
        if (!\is_string($rawId) || '' === $rawId) {
            throw new UnprocessableEntityHttpException('Field "endpoint" (a RemoteEndpoint id) is required.');
        }

        try {
            $endpointId = Uuid::fromString($rawId);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException('RemoteEndpoint was not found.');
        }

        $endpoint = $this->endpoints->findById($endpointId);
        if (!$endpoint instanceof RemoteEndpoint
            || $endpoint->getConnectionId()->toRfc4122() !== $connection->getId()->toRfc4122()) {
            throw new NotFoundHttpException('RemoteEndpoint was not found.');
        }

        return $endpoint;
    }
}
