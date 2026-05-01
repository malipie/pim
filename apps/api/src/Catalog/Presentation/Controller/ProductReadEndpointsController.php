<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Service\EffectiveAttributeGroupResolver;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * UI-02.5 (#295) — three lightweight read endpoints used by the
 * products list (UI-02.10 status indicators) and detail view
 * (UI-02.16 sticky header + right sidebar).
 *
 * - `GET /api/products/{id}/audit-log?limit=20` — last N changes from
 *   the DH Auditor `objects_audit` table. `diffs` JSON column is
 *   returned verbatim — the UI-02.13 `<AuditLogModal>` reads it.
 * - `GET /api/products/{id}/channels-status` — per-channel sync
 *   aggregate. MVP returns an empty list (no channel sync infra
 *   yet — Faza 1 publish flow populates it). The endpoint exists so
 *   the frontend can read the contract today and Faza 1 is a pure
 *   data-side switch.
 * - `GET /api/products/{id}/effective-attribute-groups` — proxy over
 *   `EffectiveAttributeGroupResolver` (UI-08.4) for product-context
 *   form rendering (UI-02.17 dynamic form).
 *
 * All three are tenant-gated through the existing TenantFilter on
 * `CatalogObject` reads — the resolver + DBAL queries inherit it.
 */
final class ProductReadEndpointsController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    private const int AUDIT_DEFAULT_LIMIT = 20;
    private const int AUDIT_MAX_LIMIT = 200;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $objects,
        private readonly EffectiveAttributeGroupResolver $resolver,
        private readonly Connection $connection,
    ) {
    }

    #[Route(
        '/api/products/{id}/audit-log',
        name: 'pim_products_audit_log',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['GET'],
        priority: 200,
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function auditLog(string $id, Request $request): JsonResponse
    {
        $product = $this->mustFindProduct($id);

        $rawLimit = $request->query->getInt('limit', self::AUDIT_DEFAULT_LIMIT);
        $limit = max(1, min(self::AUDIT_MAX_LIMIT, $rawLimit));

        $rows = $this->connection->fetchAllAssociative(
            'SELECT type, blame_user, diffs, created_at
             FROM objects_audit
             WHERE object_id = :objectId
             ORDER BY created_at DESC
             LIMIT :limit',
            [
                'objectId' => $product->getId()->toRfc4122(),
                'limit' => $limit,
            ],
            [
                'limit' => ParameterType::INTEGER,
            ],
        );

        $entries = [];
        foreach ($rows as $row) {
            $diffs = $row['diffs'] ?? null;
            $entries[] = [
                'type' => $row['type'] ?? null,
                'user' => $row['blame_user'] ?? null,
                'diffs' => \is_string($diffs) ? json_decode($diffs, true) : null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }

        return new JsonResponse([
            'product_id' => $product->getId()->toRfc4122(),
            'entries' => $entries,
        ]);
    }

    #[Route(
        '/api/products/{id}/channels-status',
        name: 'pim_products_channels_status',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['GET'],
        priority: 200,
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function channelsStatus(string $id): JsonResponse
    {
        $product = $this->mustFindProduct($id);

        // Per-channel sync rows ship with the Faza 1 publish flow; the
        // MVP aggregate column on `objects` lives in UI-02.1 but the
        // per-channel breakdown table is not in the schema yet. Until
        // it is, the endpoint exists with a stable shape so the
        // frontend (UI-02.10 / UI-02.16 right sidebar) can read it.
        return new JsonResponse([
            'product_id' => $product->getId()->toRfc4122(),
            'aggregate' => $product->getSyncStatusAggregate(),
            'channels' => [],
        ]);
    }

    #[Route(
        '/api/products/{id}/effective-attribute-groups',
        name: 'pim_products_effective_attribute_groups',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['GET'],
        priority: 200,
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function effectiveAttributeGroups(string $id): JsonResponse
    {
        $product = $this->mustFindProduct($id);

        $groups = $this->resolver->resolve($product);
        $byGroup = $this->resolver->loadGroupAttributes($groups);

        $effective = [];
        foreach ($groups as $position => $group) {
            $junctions = $byGroup[$group->getId()->toRfc4122()] ?? [];
            $attributes = [];
            foreach ($junctions as $j) {
                $attribute = $j->getAttribute();
                $attributes[] = [
                    'id' => $attribute->getId()->toRfc4122(),
                    'code' => $attribute->getCode(),
                    'type' => $attribute->getType()->value,
                    'label' => $attribute->getLabel(),
                    'is_system' => $attribute->isSystem(),
                    'position' => $j->getPosition(),
                    'is_required_in_group' => $j->isRequiredInGroup(),
                    'visible_when' => $j->getVisibleWhen(),
                ];
            }
            $effective[] = [
                'id' => $group->getId()->toRfc4122(),
                'code' => $group->getCode(),
                'label' => $group->getLabel(),
                'icon' => $group->getIcon(),
                'color' => $group->getColor(),
                'is_system_group' => $group->isSystemGroup(),
                'position' => $position,
                'attributes' => $attributes,
            ];
        }

        return new JsonResponse([
            'product_id' => $product->getId()->toRfc4122(),
            'groups' => $effective,
        ]);
    }

    private function mustFindProduct(string $id): CatalogObject
    {
        $product = $this->objects->findById(Uuid::fromString($id));
        if (!$product instanceof CatalogObject || ObjectKind::Product !== $product->getKind()) {
            throw new NotFoundHttpException(\sprintf('Product %s not found.', $id));
        }

        return $product;
    }
}
