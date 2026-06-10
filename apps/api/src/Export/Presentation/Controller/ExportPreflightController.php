<?php

declare(strict_types=1);

namespace App\Export\Presentation\Controller;

use App\Catalog\Application\Filter\FilterDslResolver;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Export\Domain\Enum\ExportEntityType;
use App\Export\Domain\Enum\ExportTargetScope;
use App\Export\Presentation\Support\ExportEntityTypeResolver;
use App\Export\Presentation\Support\ExportEntityTypeSelection;
use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Shared\Application\TenantContext;
use App\Shared\Domain\Tenant;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * EXR-07 (#1383) — preflight count + sync/async routing contract.
 *
 * The wizard needs a cheap "how many rows will this export?" probe before
 * running, and the resulting sync-vs-async decision (so Krok 2/4 can show the
 * live counter + asynchronicity note). This endpoint runs a COUNT only — no
 * side effects — using the same FilterDSL the product list uses and the same
 * {@see SyncExportController::SYNC_THRESHOLD} constant the runner routes on
 * (single source of truth; the UI never hardcodes 100).
 *
 * Scope (EXR-07): `product` and `custom_module`. Structural entity types
 * always export the full configuration set; their preflight count lands with
 * the structural builders in EXR-06.
 */
final class ExportPreflightController
{
    public function __construct(
        private readonly ExportEntityTypeResolver $entityTypeResolver,
        private readonly ObjectTypeRepositoryInterface $objectTypes,
        private readonly FilterDslResolver $filterDsl,
        private readonly Connection $connection,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[Route(
        path: '/api/exports/preflight',
        name: 'pim_export_preflight',
        methods: ['POST'],
    )]
    #[IsGranted('ROLE_USER')]
    #[RequiresPermission(module: 'exports', action: 'run')]
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new AccessDeniedHttpException('Tenant context required.');
        }

        $payload = $this->decodeJson($request);
        $selection = $this->entityTypeResolver->resolve($payload);
        $scope = $this->parseScope($payload);
        $this->entityTypeResolver->assertScopeAllowed($selection->entityType, $scope);

        if ($selection->entityType->isStructural()) {
            // Structural exports run with target_scope=all and their row count
            // comes from the structural builders (EXR-06).
            throw new UnprocessableEntityHttpException(
                'Preflight count for structural entity types is added in EXR-06.',
            );
        }

        $objectType = $this->resolveObjectType($selection, $tenant);

        $count = match ($scope) {
            ExportTargetScope::Selected => $this->countSelected($payload),
            ExportTargetScope::All => $this->countAll($tenant, $objectType),
            ExportTargetScope::Filter => $this->countFilter($payload, $tenant, $objectType),
        };

        $mode = $count >= SyncExportController::SYNC_THRESHOLD ? 'async' : 'sync';

        return new JsonResponse([
            'count' => $count,
            'mode' => $mode,
            'threshold' => SyncExportController::SYNC_THRESHOLD,
            'soft_cap' => SyncExportController::SOFT_CAP,
            'exceeds_cap' => $count > SyncExportController::SOFT_CAP,
        ]);
    }

    private function resolveObjectType(ExportEntityTypeSelection $selection, Tenant $tenant): ObjectType
    {
        if (ExportEntityType::Product === $selection->entityType) {
            $builtIn = $this->objectTypes->findBuiltInByKind(ObjectKind::Product, $tenant);
            if (null === $builtIn) {
                throw new UnprocessableEntityHttpException('Built-in Product ObjectType is not seeded for this tenant.');
            }

            return $builtIn;
        }

        // custom_module — object_type_id already validated by the resolver.
        $id = $selection->objectTypeId;
        \assert($id instanceof Uuid);
        $objectType = $this->objectTypes->findById($id);
        \assert($objectType instanceof ObjectType);

        return $objectType;
    }

    private function countAll(Tenant $tenant, ObjectType $objectType): int
    {
        return $this->runCount(
            'SELECT COUNT(*) FROM catalog_objects co WHERE co.tenant_id = :tenant AND co.object_type_id = :otid AND co.deleted_at IS NULL',
            ['tenant' => $tenant->getId()->toRfc4122(), 'otid' => $objectType->getId()->toRfc4122()],
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function countFilter(array $payload, Tenant $tenant, ObjectType $objectType): int
    {
        $dsl = $this->parseFilter($payload);
        if (null === $dsl || [] === $dsl) {
            return 0;
        }
        $whereClause = $this->filterDsl->toCountSql($dsl);
        if (null === $whereClause) {
            throw new BadRequestHttpException('Invalid filter DSL.');
        }

        return $this->runCount(
            'SELECT COUNT(*) FROM catalog_objects co WHERE co.tenant_id = :tenant AND co.object_type_id = :otid AND co.deleted_at IS NULL AND ('.$whereClause.')',
            ['tenant' => $tenant->getId()->toRfc4122(), 'otid' => $objectType->getId()->toRfc4122()],
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function countSelected(array $payload): int
    {
        $value = $payload['selected_ids'] ?? null;
        if (!\is_array($value)) {
            throw new BadRequestHttpException('selected_ids must be an array when target_scope=selected.');
        }
        $ids = [];
        foreach ($value as $id) {
            if (!\is_string($id) || !Uuid::isValid($id)) {
                throw new BadRequestHttpException('selected_ids must contain RFC 4122 UUID strings.');
            }
            $ids[$id] = true;
        }

        return \count($ids);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function runCount(string $sql, array $params): int
    {
        try {
            $result = $this->connection->fetchOne($sql, $params);

            return \is_numeric($result) ? (int) $result : 0;
        } catch (Throwable $error) {
            throw new BadRequestHttpException('Preflight count failed: '.$error->getMessage(), $error);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function parseScope(array $payload): ExportTargetScope
    {
        $value = $payload['target_scope'] ?? null;
        if (!\is_string($value)) {
            throw new BadRequestHttpException('target_scope is required (selected|filter|all).');
        }
        $scope = ExportTargetScope::tryFrom($value);
        if (null === $scope) {
            throw new BadRequestHttpException(sprintf('Unsupported target_scope "%s".', $value));
        }

        return $scope;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>|null
     */
    private function parseFilter(array $payload): ?array
    {
        $value = $payload['filter'] ?? null;
        if (null === $value) {
            return null;
        }
        if (!\is_array($value)) {
            throw new BadRequestHttpException('filter must be a JSON object or null.');
        }
        $dsl = [];
        foreach ($value as $key => $val) {
            if (!\is_string($key)) {
                throw new BadRequestHttpException('filter must be a JSON object (string keys).');
            }
            $dsl[$key] = $val;
        }

        return $dsl;
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(Request $request): array
    {
        $body = $request->getContent();
        if ('' === $body) {
            return [];
        }
        $decoded = json_decode($body, true);
        if (!\is_array($decoded)) {
            throw new BadRequestHttpException('Request body must be a JSON object.');
        }
        $payload = [];
        foreach ($decoded as $key => $value) {
            if (!\is_string($key)) {
                throw new BadRequestHttpException('Request body must be a JSON object (string keys).');
            }
            $payload[$key] = $value;
        }

        return $payload;
    }
}
