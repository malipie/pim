<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\ObjectTypeService;
use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-01b (#413) — `POST` / `DELETE` `/api/object_types/{id}/attributes/{attributeId}`
 * for the modeling Detail view's "Custom attribute" card. Lets the operator
 * attach a single Attribute directly to an ObjectType (bypassing the
 * AttributeGroup pathway) — junction lives in `object_type_attributes`,
 * the same table powering ADR-009 reuse of attributes across kinds.
 *
 * `bulk-attach` accepts `{ attributeIds: string[] }` so the picker UI
 * (`AddAttributesToObjectTypeDialog`) submits a multi-pick in one request
 * — same shape as `AttributeGroupAttribute` bulk-attach for consistency.
 *
 * Idempotency: re-attaching is a no-op (junction.upsert via
 * `ObjectTypeService::assignAttribute`); detaching missing junction
 * returns 204 to keep retries safe.
 */
final class AttachObjectTypeAttributeController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly ObjectTypeRepositoryInterface $objectTypes,
        private readonly AttributeRepositoryInterface $attributes,
        private readonly ObjectTypeService $service,
        private readonly Connection $connection,
    ) {
    }

    #[Route(
        '/api/object_types/{id}/attributes/{attributeId}',
        name: 'pim_object_types_attach_attribute',
        requirements: ['id' => self::UUID_REGEX, 'attributeId' => self::UUID_REGEX],
        methods: ['POST'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function attach(string $id, string $attributeId): JsonResponse
    {
        $objectType = $this->objectTypes->findById(Uuid::fromString($id));
        if (null === $objectType) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $id));
        }

        $attribute = $this->attributes->findById(Uuid::fromString($attributeId));
        if (null === $attribute) {
            throw new NotFoundHttpException(\sprintf('Attribute "%s" was not found.', $attributeId));
        }

        $sortOrder = $this->nextSortOrder($id);
        $this->service->assignAttribute($objectType, $attribute, false, $sortOrder);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/api/object_types/{id}/attributes/{attributeId}',
        name: 'pim_object_types_detach_attribute',
        requirements: ['id' => self::UUID_REGEX, 'attributeId' => self::UUID_REGEX],
        methods: ['DELETE'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function detach(string $id, string $attributeId): JsonResponse
    {
        $objectType = $this->objectTypes->findById(Uuid::fromString($id));
        if (null === $objectType) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $id));
        }

        $attribute = $this->attributes->findById(Uuid::fromString($attributeId));
        if (null === $attribute) {
            // Idempotent on the attribute side — already gone is success.
            return new JsonResponse(null, Response::HTTP_NO_CONTENT);
        }

        $this->service->unassignAttribute($objectType, $attribute);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route(
        '/api/object_types/{id}/attributes/bulk-attach',
        name: 'pim_object_types_bulk_attach_attributes',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['POST'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function bulkAttach(string $id, Request $request): JsonResponse
    {
        $objectType = $this->objectTypes->findById(Uuid::fromString($id));
        if (null === $objectType) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $id));
        }

        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            throw new BadRequestHttpException('Body must be a JSON object.');
        }
        $rawIds = $payload['attributeIds'] ?? null;
        if (!\is_array($rawIds)) {
            throw new BadRequestHttpException('Field "attributeIds" must be an array of UUIDs.');
        }

        $sortOrder = $this->nextSortOrder($id);
        foreach ($rawIds as $rawId) {
            if (!\is_string($rawId) || !Uuid::isValid($rawId)) {
                throw new BadRequestHttpException(\sprintf('Invalid UUID in "attributeIds": %s', \is_string($rawId) ? $rawId : '(non-string)'));
            }
            $attribute = $this->attributes->findById(Uuid::fromString($rawId));
            if (null === $attribute) {
                throw new NotFoundHttpException(\sprintf('Attribute "%s" was not found.', $rawId));
            }
            $this->service->assignAttribute($objectType, $attribute, false, $sortOrder);
            ++$sortOrder;
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Append-style ordering: new junctions land at the end of the existing
     * list. DBAL is cheaper than hydrating the full collection through
     * Doctrine just to find a max.
     */
    private function nextSortOrder(string $objectTypeId): int
    {
        $raw = $this->connection->fetchOne(
            'SELECT COALESCE(MAX(sort_order), -1) + 1 FROM object_type_attributes WHERE object_type_id = ?',
            [$objectTypeId],
        );

        return \is_scalar($raw) ? (int) $raw : 0;
    }
}
