<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-01 (#372) — `POST` / `DELETE` `/api/object_types/{id}/groups/{groupId}`
 * for the modeling Detail view's "Add attribute group" button + per-row
 * trash icon. Idempotent semantics on POST (re-attach is a no-op),
 * tolerant on DELETE (already-detached returns 204).
 *
 * Built-in junction protection: system groups (`is_system_group=true`)
 * cannot be detached unless they are legacy `audit` rows from the previous
 * auto-attached model. Audit visibility is now explicit modeling config.
 */
final class AttachObjectTypeAttributeGroupController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly ObjectTypeRepositoryInterface $objectTypes,
        private readonly AttributeGroupRepositoryInterface $attributeGroups,
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
    ) {
    }

    #[Route(
        '/api/object_types/{id}/groups/{groupId}',
        name: 'pim_object_types_attach_group',
        requirements: ['id' => self::UUID_REGEX, 'groupId' => self::UUID_REGEX],
        methods: ['POST'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'modeling.object_types', action: 'add')]
    public function attach(string $id, string $groupId): JsonResponse
    {
        $objectType = $this->objectTypes->findById(Uuid::fromString($id));
        if (null === $objectType) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $id));
        }

        $group = $this->attributeGroups->findById(Uuid::fromString($groupId));
        if (null === $group) {
            throw new NotFoundHttpException(\sprintf('AttributeGroup "%s" was not found.', $groupId));
        }

        // Closed system types (amends ADR-009) — Asset / Category schemas are
        // platform-managed and expose no user-attachable attribute groups.
        if (!$objectType->getKind()->isAttributeModelable()) {
            throw new HttpException(
                422,
                \sprintf(
                    'ObjectType "%s" (kind=%s) is a closed system type and cannot have attribute groups attached; its schema is platform-managed.',
                    $objectType->getCode(),
                    $objectType->getKind()->value,
                ),
            );
        }

        // Idempotent — DBAL existence check is cheaper than fetching the
        // junction entity through the EntityManager.
        $existing = $this->connection->fetchOne(
            'SELECT 1 FROM object_type_attribute_groups WHERE object_type_id = ? AND attribute_group_id = ?',
            [$id, $groupId],
        );
        if (false === $existing) {
            $junction = new ObjectTypeAttributeGroup($objectType, $group);
            $this->em->persist($junction);
            $this->em->flush();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/api/object_types/{id}/groups/{groupId}',
        name: 'pim_object_types_detach_group',
        requirements: ['id' => self::UUID_REGEX, 'groupId' => self::UUID_REGEX],
        methods: ['DELETE'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'modeling.object_types', action: 'add')]
    public function detach(string $id, string $groupId): JsonResponse
    {
        $group = $this->attributeGroups->findById(Uuid::fromString($groupId));
        if (null === $group) {
            // Already gone — idempotent on the group side.
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }
        if ($group->isSystemGroup() && 'audit' !== $group->getCode()) {
            throw new HttpException(
                Response::HTTP_FORBIDDEN,
                \sprintf('AttributeGroup "%s" is a system group and cannot be detached.', $group->getCode()),
            );
        }

        // tenant-safe: junction table inherits tenant via FK chain.
        // Both $id (ObjectType) and $groupId (AttributeGroup) were
        // resolved through TenantFilter-aware repositories above
        // (lines 50, 87) — they cannot reference rows from other
        // tenants by the time we reach the DELETE.
        $this->connection->executeStatement(
            'DELETE FROM object_type_attribute_groups WHERE object_type_id = ? AND attribute_group_id = ?',
            [$id, $groupId],
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * MODR-01 (#923) — `PATCH /api/object_types/{id}/groups/{groupId}` updates
     * the `display_mode` of an existing junction without recreating it.
     * Body shape: `{ "display_mode": "tab"|"stacked" }`.
     */
    #[Route(
        '/api/object_types/{id}/groups/{groupId}',
        name: 'pim_object_types_patch_group_assignment',
        requirements: ['id' => self::UUID_REGEX, 'groupId' => self::UUID_REGEX],
        methods: ['PATCH'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'modeling.object_types', action: 'add')]
    public function patch(string $id, string $groupId, Request $request): JsonResponse
    {
        $objectType = $this->objectTypes->findById(Uuid::fromString($id));
        if (null === $objectType) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $id));
        }

        $group = $this->attributeGroups->findById(Uuid::fromString($groupId));
        if (null === $group) {
            throw new NotFoundHttpException(\sprintf('AttributeGroup "%s" was not found.', $groupId));
        }

        $payload = json_decode($request->getContent(), true);
        $hasDisplayMode = \is_array($payload) && \array_key_exists('display_mode', $payload);
        $hasPosition = \is_array($payload) && \array_key_exists('position', $payload);
        if (!$hasDisplayMode && !$hasPosition) {
            throw new BadRequestHttpException('Body must contain `display_mode` and/or `position`.');
        }

        $displayMode = null;
        if ($hasDisplayMode) {
            $displayMode = $payload['display_mode'];
            if (!\is_string($displayMode) || !\in_array($displayMode, ObjectTypeAttributeGroup::DISPLAY_MODES, true)) {
                throw new BadRequestHttpException(\sprintf(
                    'display_mode must be one of: %s.',
                    implode(', ', ObjectTypeAttributeGroup::DISPLAY_MODES),
                ));
            }
        }

        // #1349 — reordering attribute groups within an ObjectType. The
        // persisted `position` drives the left-to-right tab order on the
        // object detail page (EffectiveAttributeGroupResolver orders by
        // `position ASC`).
        $position = null;
        if ($hasPosition) {
            $position = $payload['position'];
            if (!\is_int($position) || $position < 0) {
                throw new BadRequestHttpException('position must be a non-negative integer.');
            }
        }

        $junction = $this->em->find(ObjectTypeAttributeGroup::class, [
            'objectType' => $objectType,
            'attributeGroup' => $group,
        ]);
        if (!$junction instanceof ObjectTypeAttributeGroup) {
            throw new NotFoundHttpException(\sprintf(
                'AttributeGroup "%s" is not attached to ObjectType "%s".',
                $groupId,
                $id,
            ));
        }

        if (null !== $displayMode) {
            $junction->changeDisplayMode($displayMode);
        }
        if (null !== $position) {
            $junction->reorder($position);
        }
        $this->em->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
