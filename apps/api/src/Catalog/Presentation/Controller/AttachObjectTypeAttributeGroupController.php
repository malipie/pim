<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Domain\Entity\ObjectTypeAttributeGroup;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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
 * cannot be detached — they are platform-mandated for every ObjectType.
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
    public function detach(string $id, string $groupId): JsonResponse
    {
        $group = $this->attributeGroups->findById(Uuid::fromString($groupId));
        if (null === $group) {
            // Already gone — idempotent on the group side.
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }
        if ($group->isSystemGroup()) {
            throw new HttpException(
                Response::HTTP_FORBIDDEN,
                \sprintf('AttributeGroup "%s" is a system group and cannot be detached.', $group->getCode()),
            );
        }

        $this->connection->executeStatement(
            'DELETE FROM object_type_attribute_groups WHERE object_type_id = ? AND attribute_group_id = ?',
            [$id, $groupId],
        );

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
