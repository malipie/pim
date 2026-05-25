<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Domain\Repository\AttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeAttributeRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * ULV-10 (#992) — `PATCH /api/object_types/{id}/attributes/{attributeId}`
 * for the universal list column configuration.
 *
 * Body shape:
 * ```json
 * { "show_in_list": true, "list_position": 5 }
 * ```
 *
 * Both fields are optional; absent fields leave the junction value
 * unchanged. The endpoint is foundation for the ObjectType wizard
 * "Attributes" step (ULV-10) — the wizard UI itself is deferred to a
 * follow-up because the existing wizard surface
 * (`/modeling/object-types/...`) needs a dedicated drag-and-drop
 * reorder + per-attribute toggle layout that did not ship with this
 * marathon slice.
 *
 * Validates: ObjectType + Attribute exist in the current tenant, the
 * junction exists, list_position is a non-negative integer, show_in_list
 * is a boolean.
 */
final class PatchObjectTypeAttributeListConfigController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly ObjectTypeRepositoryInterface $objectTypes,
        private readonly AttributeRepositoryInterface $attributes,
        private readonly ObjectTypeAttributeRepositoryInterface $junctions,
    ) {
    }

    #[Route(
        '/api/object_types/{id}/attributes/{attributeId}/list-config',
        name: 'pim_object_types_attribute_list_config',
        requirements: ['id' => self::UUID_REGEX, 'attributeId' => self::UUID_REGEX],
        methods: ['PATCH'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'modeling.object_types', action: 'add')]
    public function __invoke(string $id, string $attributeId, Request $request): JsonResponse
    {
        $objectType = $this->objectTypes->findById(Uuid::fromString($id));
        if (null === $objectType) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $id));
        }

        $attribute = $this->attributes->findById(Uuid::fromString($attributeId));
        if (null === $attribute) {
            throw new NotFoundHttpException(\sprintf('Attribute "%s" was not found.', $attributeId));
        }

        $junction = $this->junctions->findOne($objectType, $attribute);
        if (null === $junction) {
            throw new NotFoundHttpException(\sprintf(
                'Attribute "%s" is not attached to ObjectType "%s".',
                $attributeId,
                $id,
            ));
        }

        $body = json_decode($request->getContent(), true);
        if (!\is_array($body)) {
            throw new BadRequestHttpException('Body must be a JSON object.');
        }

        if (\array_key_exists('show_in_list', $body)) {
            $value = $body['show_in_list'];
            if (!\is_bool($value)) {
                throw new BadRequestHttpException('Field "show_in_list" must be a boolean.');
            }
            $junction->setShowInList($value);
        }

        if (\array_key_exists('list_position', $body)) {
            $value = $body['list_position'];
            if (!\is_int($value) || $value < 0) {
                throw new BadRequestHttpException('Field "list_position" must be a non-negative integer.');
            }
            $junction->setListPosition($value);
        }

        $this->junctions->save($junction);

        return new JsonResponse(
            [
                'attribute_id' => $attributeId,
                'object_type_id' => $id,
                'show_in_list' => $junction->isShownInList(),
                'list_position' => $junction->getListPosition(),
            ],
            Response::HTTP_OK,
        );
    }
}
