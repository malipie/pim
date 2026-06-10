<?php

declare(strict_types=1);

namespace App\Export\Presentation\Controller;

use App\Export\Application\Builder\Structural\StructuralExportBuilderInterface;
use App\Export\Domain\Enum\ExportEntityType;
use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Shared\Application\TenantContext;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * EXR-11 (#1387) — column catalog per export entity type.
 *
 * Structural entity types expose their fixed, ordered column set from the
 * matching EXR-06 builder (locale fan-out already applied server-side).
 * `custom_module` returns the attribute codes attached to the ObjectType
 * (object_type_attributes junction) so the wizard's picker can narrow the
 * tenant-wide attribute catalog to that module. `product` keeps the
 * frontend-assembled catalog (full tenant attribute set — the existing
 * ExportModal contract), so it is intentionally NOT served here.
 */
final class ExportColumnsController
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly Connection $connection,
        /** @var iterable<StructuralExportBuilderInterface> */
        #[AutowireIterator('app.export.structural_builder')]
        private readonly iterable $structuralBuilders = [],
    ) {
    }

    #[Route(
        path: '/api/exports/columns',
        name: 'pim_export_columns',
        methods: ['GET'],
    )]
    #[IsGranted('ROLE_USER')]
    #[RequiresPermission(module: 'exports', action: 'run')]
    public function __invoke(Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new AccessDeniedHttpException('Tenant context required.');
        }

        $entityTypeRaw = $request->query->get('entity_type');
        if (!\is_string($entityTypeRaw) || '' === $entityTypeRaw) {
            throw new BadRequestHttpException('entity_type query parameter is required.');
        }
        $entityType = ExportEntityType::tryFrom($entityTypeRaw);
        if (null === $entityType) {
            throw new BadRequestHttpException(sprintf('Unsupported entity_type "%s".', $entityTypeRaw));
        }

        if ($entityType->isStructural()) {
            foreach ($this->structuralBuilders as $builder) {
                if ($builder->supports($entityType)) {
                    return new JsonResponse([
                        'entity_type' => $entityType->value,
                        'columns' => $builder->columns($tenant),
                    ]);
                }
            }

            throw new UnprocessableEntityHttpException(sprintf('No structural builder for entity_type=%s.', $entityType->value));
        }

        if (ExportEntityType::CustomModule !== $entityType) {
            throw new BadRequestHttpException('product uses the frontend attribute catalog; only custom_module and structural types are served here.');
        }

        $objectTypeIdRaw = $request->query->get('object_type_id');
        if (!\is_string($objectTypeIdRaw) || !Uuid::isValid($objectTypeIdRaw)) {
            throw new BadRequestHttpException('object_type_id (uuid) is required for custom_module.');
        }

        /** @var list<string> $codes */
        $codes = $this->connection->fetchFirstColumn(
            <<<'SQL'
                SELECT a.code
                FROM object_type_attributes ota
                JOIN attributes a ON a.id = ota.attribute_id
                JOIN object_types ot ON ot.id = ota.object_type_id
                WHERE ota.object_type_id = :objectTypeId
                  AND a.tenant_id = :tenantId
                  AND ot.tenant_id = :tenantId
                ORDER BY ota.sort_order, a.code
                SQL,
            [
                'objectTypeId' => $objectTypeIdRaw,
                'tenantId' => $tenant->getId()->toRfc4122(),
            ],
        );

        return new JsonResponse([
            'entity_type' => $entityType->value,
            'attribute_codes' => $codes,
        ]);
    }
}
